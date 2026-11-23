import {defineConfig} from "vite";
import {viteStaticCopy} from "vite-plugin-static-copy";
import vue from "@vitejs/plugin-vue";
import AutoImport from "unplugin-auto-import/vite";
import Components from "unplugin-vue-components/vite";
import {ElementPlusResolver} from "unplugin-vue-components/resolvers";
import path from "path";
import fs from "fs";
import pruneElementGrid from "./dev/vite/prune-element-grid.mjs";
import dedupeCssRules from "./dev/vite/dedupe-css-rules.mjs";

const serverConfig = require('./config/vite.json');

function getPluginVersion() {
    const pluginFile = fs.readFileSync(path.resolve(__dirname, 'fluent-support.php'), 'utf8');
    const match = pluginFile.match(/define\s*\(\s*['"]FLUENT_SUPPORT_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/);
    return match ? match[1] : '1.0.0';
}

const PLUGIN_VERSION = getPluginVersion();

// File names stay the same across releases (clean SVN diffs); every import
// between chunks carries ?ver=PLUGIN_VERSION instead. No chunk imports
// start.js: the app is the separate `app` chunk (see manualChunks), so the
// entry keeps one URL, the one in its <script> tag.
const chunkCacheBustPlugin = {
    name: 'chunk-cache-bust',
    // writeBundle, on the written files: Vite removes imports of the CSS-only chunks
    // it deletes, and builds each dynamic import's preload list, in its own
    // generateBundle, which runs after this plugin's. A ?ver= added before then
    // hides the import from both steps (dangling imports, empty preload lists).
    writeBundle(options, bundle) {
        const ver = '?ver=' + PLUGIN_VERSION;
        const addVer = (match, prefix, file, suffix) => `${prefix}${file}${ver}${suffix}`;
        for (const file of Object.values(bundle)) {
            if (file.type !== 'chunk') {
                continue;
            }
            const filePath = path.join(options.dir, file.fileName);
            const code = fs.readFileSync(filePath, 'utf8')
                .replace(/(from\s*["'])(\.{1,2}\/[^"'?]+\.js)(["'])/g, addVer)
                .replace(/(import\s*["'])(\.{1,2}\/[^"'?]+\.js)(["'])/g, addVer)
                .replace(/(import\(\s*["'])(\.{1,2}\/[^"'?]+\.js)(["'])/g, addVer)
                // Vite's preload list, so a preload and its import share one URL.
                // Only inside __vite__mapDeps: other strings in the code stay as they are.
                .replace(/(__vite__mapDeps=[^\[]*\[)([^\]]*)(\])/, (match, start, list, end) =>
                    start + list.replace(/(["'])(\.{1,2}\/[^"'?]+\.js)(["'])/g, addVer) + end
                );
            fs.writeFileSync(filePath, code);
        }
    }
};

const appModuleId = path.resolve(__dirname, 'resources/admin/app.js');

// FluentCommunity's shared runtime (@fluentcommunity/vue, /element-plus, /ui): its portal
// page maps these through an import map, so add-on scripts import them and never bundle a copy.
// The build keeps the bare imports. The dev server rewrites them to /@id/@fluentcommunity/x,
// so it answers that path with a re-export, which the browser resolves through the same map.
const fluentCommunityRuntimePlugin = {
    name: 'fluent-community-runtime',
    enforce: 'pre',
    resolveId(id) {
        if (id.startsWith('@fluentcommunity/')) {
            return {id, external: true};
        }
        return null;
    },
    configureServer(server) {
        server.middlewares.use((req, res, next) => {
            const match = /^\/@id\/(@fluentcommunity\/[a-z-]+)(?:\?|$)/.exec(req.url || '');
            if (!match) {
                return next();
            }
            res.setHeader('Content-Type', 'text/javascript');
            res.setHeader('Access-Control-Allow-Origin', '*');
            res.end(`export * from '${match[1]}';`);
        });
    }
};

// Fail the build when a chunk imports a file the bundle does not contain
// (for example a CSS-only chunk Vite removed), instead of shipping a page that 404s.
const verifyChunkImportsPlugin = {
    name: 'verify-chunk-imports',
    writeBundle(options, bundle) {
        const missing = [];
        for (const file of Object.values(bundle)) {
            if (file.type !== 'chunk') {
                continue;
            }
            const dir = path.posix.dirname(file.fileName);
            for (const match of file.code.matchAll(/(?:from|import)\s*\(?\s*["'](\.{1,2}\/[^"'?]+\.js)/g)) {
                const target = path.posix.normalize(path.posix.join(dir, match[1]));
                if (!bundle[target]) {
                    missing.push(`${file.fileName} -> ${match[1]}`);
                }
            }
            // Preload lists (__vite__mapDeps) name CSS files by relative path.
            for (const match of file.code.matchAll(/["']((?:\.{1,2}\/)+[^"'?]+\.css)["']/g)) {
                const target = path.posix.normalize(path.posix.join(dir, match[1]));
                if (!bundle[target]) {
                    missing.push(`${file.fileName} -> ${match[1]}`);
                }
            }
        }
        if (missing.length) {
            this.error('Chunks import files that were not emitted:\n' + missing.join('\n'));
        }
    }
};

// Entry points (admin app + standalone scripts)
// Customer portal is built separately via vite.config.portal.mjs as a standalone bundle
const inputs = [
    "resources/admin/start.js",
    "resources/admin/global_admin.js",
    "resources/admin/global_summary.js",
    "resources/admin/community_ticket_menu.js",
    "resources/customer_portal/login_helper.js",
    "resources/scss/alpha-admin.scss",
    "resources/scss/all_public.scss",
];

// Map Vite source-relative paths to desired output paths.
// Admin JS goes into admin/js/ and portal JS into portal/js/ to match release structure.
function mapOutputPath(srcRelativePath) {
    const specificMap = {
        'customer_portal/login_helper.js': 'portal/js/login_helper.js',
    };
    if (specificMap[srcRelativePath]) return specificMap[srcRelativePath];
    if (srcRelativePath.startsWith('admin/')) {
        return 'admin/js/' + srcRelativePath.slice('admin/'.length);
    }
    return srcRelativePath;
}

// Keep legacy CSS output locations so existing PHP enqueue paths and relative font URLs continue to work.
const legacyCssOutputMap = {
    "resources/scss/alpha-admin.scss": "admin/css/alpha-admin.css",
    "resources/scss/all_public.scss": "admin/css/all_public.css",
};

// Merge all chunk CSS (from Vue components / JS imports) into alpha-admin.css.
// Standalone SCSS entries (legacyCssOutputMap) keep their own output files.
const legacyCssPaths = new Set(Object.values(legacyCssOutputMap));
const mergeCssChunksPlugin = {
    name: 'merge-css-chunks',
    enforce: 'post',
    generateBundle(_, bundle) {
        let mergedCss = '';
        const toRemove = [];

        // Merge in load order: a chunk's static imports before the chunk, so an
        // Element Plus base rule comes before the component CSS that overrides it.
        const chunks = Object.values(bundle).filter(file => file.type === 'chunk');
        const chunkByFile = new Map(chunks.map(chunk => [chunk.fileName, chunk]));
        const orderedCss = [];
        const visited = new Set();
        const visit = (chunk) => {
            if (!chunk || visited.has(chunk.fileName)) {
                return;
            }
            visited.add(chunk.fileName);
            chunk.imports.forEach(fileName => visit(chunkByFile.get(fileName)));
            const importedCss = chunk.viteMetadata && chunk.viteMetadata.importedCss;
            if (importedCss) {
                importedCss.forEach(fileName => orderedCss.push(fileName));
            }
            chunk.dynamicImports.forEach(fileName => visit(chunkByFile.get(fileName)));
        };
        chunks.filter(chunk => chunk.isEntry).forEach(visit);
        chunks.forEach(visit);

        const cssFiles = Object.keys(bundle).filter(fileName => bundle[fileName].type === 'asset' && fileName.endsWith('.css') && !legacyCssPaths.has(fileName));
        const mergeOrder = [...new Set(orderedCss.filter(fileName => cssFiles.includes(fileName)))];
        cssFiles.forEach(fileName => {
            if (!mergeOrder.includes(fileName)) {
                mergeOrder.push(fileName);
            }
        });

        for (const fileName of mergeOrder) {
            mergedCss += bundle[fileName].source + '\n';
            toRemove.push(fileName);
        }

        // Vite builds each dynamic import's preload list from importedCss in its own
        // generateBundle, which runs after this plugin. Left in, the merged files
        // would be preloaded, 404, and reject the route import ("Unable to preload CSS").
        const mergedFiles = new Set(toRemove);
        chunks.forEach(chunk => {
            const importedCss = chunk.viteMetadata && chunk.viteMetadata.importedCss;
            if (importedCss) {
                mergedFiles.forEach(fileName => importedCss.delete(fileName));
            }
        });

        for (const f of toRemove) delete bundle[f];

        if (mergedCss) {
            // Append component/module CSS into alpha-admin.css to avoid a separate style.css file.
            const targetKey = 'admin/css/alpha-admin.css';
            if (bundle[targetKey] && bundle[targetKey].type === 'asset') {
                bundle[targetKey].source += '\n' + mergedCss;
            } else {
                this.emitFile({ type: 'asset', fileName: targetKey, source: mergedCss });
            }
        }
    }
};

// Extract license comments from minified chunks and write them to .LICENSE.txt files,
// replicating the webpack TerserPlugin `extractComments: true` behaviour.
// Matches: /*! ... */ and /** ... @license/@preserve/@cc_on ... */
function createExtractLicensesPlugin() {
    const licenseMap = new Map();
    return {
        name: 'extract-licenses',
        enforce: 'post', // Run after Vite's internal terser plugin
        renderChunk(code, chunk) {
            const blockCommentRegex = /\/\*[\s\S]*?\*\//g;
            const seen = new Set();
            const licenseComments = [];
            let match;
            while ((match = blockCommentRegex.exec(code)) !== null) {
                const comment = match[0];
                if (/^\/\*!/.test(comment) || /@license|@preserve|@cc_on/i.test(comment)) {
                    if (!seen.has(comment)) {
                        seen.add(comment);
                        licenseComments.push(comment);
                    }
                }
            }
            if (!licenseComments.length) return null;
            licenseMap.set(chunk.fileName, licenseComments.join('\n\n'));
            // Strip all matched license comments from the output JS
            const licenseRegex = /\/\*![\s\S]*?\*\//g;
            return { code: code.replace(licenseRegex, '').trimStart(), map: null };
        },
        generateBundle(_, bundle) {
            for (const [fileName, content] of licenseMap.entries()) {
                if (bundle[fileName]) {
                    this.emitFile({ type: 'asset', fileName: fileName + '.LICENSE.txt', source: content });
                }
            }
        },
    };
}

let viteConfig;

const moveManifestPlugin = {
    name: "move-manifest",
    configResolved(resolvedConfig) {
        viteConfig = resolvedConfig;
    },
    writeBundle() {
        const outDir = viteConfig.build.outDir;
        const manifestSrc = path.join(outDir, ".vite", "manifest.json");
        const manifestDest = path.resolve(__dirname, serverConfig.manifest_path);
        const viteDir = path.join(outDir, ".vite");

        if (fs.existsSync(manifestSrc)) {
            fs.renameSync(manifestSrc, manifestDest);

            if (fs.existsSync(viteDir) && fs.readdirSync(viteDir).length === 0) {
                fs.rmSync(viteDir, {recursive: true});
            }

            const manifestContent = JSON.parse(fs.readFileSync(manifestDest, "utf8"));
            const phpArray = jsonToPhpArray(manifestContent);

            const configPath = path.resolve(__dirname, "config/vite_config.php");
            if (!fs.existsSync(configPath)) {
                fs.writeFileSync(configPath, '<?php return [];', "utf8");
            }

            fs.writeFileSync(configPath, '<?php return ' + phpArray + ';', "utf8");

            // Save JSON copy for portal build to merge with
            const jsonCopyPath = path.resolve(__dirname, "config/vite_manifest_admin.json");
            fs.writeFileSync(jsonCopyPath, JSON.stringify(manifestContent, null, 2), "utf8");

            console.log("Manifest array injected into config/vite_config.php");
        }
    },
};

function jsonToPhpArray(obj, indentLevel = 1) {
    const indent = "    ".repeat(indentLevel);
    if (Array.isArray(obj)) {
        return "[\n" +
            obj.map(v => `${indent}${valueToPhp(v, indentLevel + 1)}`).join(",\n") +
            "\n" + "    ".repeat(indentLevel - 1) + "]";
    } else if (typeof obj === "object" && obj !== null) {
        return "[\n" +
            Object.entries(obj)
                .map(([key, value]) => `${indent}'${key}' => ${valueToPhp(value, indentLevel + 1)}`)
                .join(",\n") +
            "\n" + "    ".repeat(indentLevel - 1) + "]";
    }
    return valueToPhp(obj, indentLevel);
}

function valueToPhp(value, indentLevel) {
    if (typeof value === "string") {
        return `'${value.replace(/'/g, "\\'")}'`;
    } else if (typeof value === "number") {
        return String(value);
    } else if (typeof value === "boolean") {
        return value ? "true" : "false";
    } else if (value === null) {
        return "null";
    } else if (Array.isArray(value) || typeof value === "object") {
        return jsonToPhpArray(value, indentLevel);
    }
    return "null";
}

export default defineConfig({
    base: '',
    publicDir: false,
    css: {
        preprocessorOptions: {
            scss: {
                api: "modern",
            }
        }
    },
    plugins: [
        fluentCommunityRuntimePlugin,
        AutoImport({
            resolvers: [ElementPlusResolver()],
        }),
        Components({
            resolvers: [ElementPlusResolver()],
        }),
        vue({
            template: {
                compilerOptions: {
                    compatConfig: {
                        MODE: 3
                    }
                }
            }
        }),
        viteStaticCopy({
            targets: [
                {src: "resources/images", dest: ""},
                {src: "resources/libs", dest: ""},
                // Block editor is built separately via `npm run build:block` (React/JSX, uses WP globals)
            ],
        }),
        moveManifestPlugin,
        mergeCssChunksPlugin,
        pruneElementGrid({ sourceDir: path.resolve(__dirname, "resources") }),
        dedupeCssRules(),
        createExtractLicensesPlugin(),
    ],

    build: {
        sourcemap: false,
        manifest: true,
        outDir: "assets",
        emptyOutDir: false,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
                pure_funcs: ['console.log', 'console.info', 'console.debug', 'console.trace'],
            },
            mangle: {
                safari10: true,
            }
        },
        chunkSizeWarningLimit: 1000,
        reportCompressedSize: true,
        cssCodeSplit: true,
        assetsInlineLimit: 4096,
        // JS is code-split per route; CSS is not. mergeCssChunksPlugin folds every
        // chunk's CSS into alpha-admin.css and removes those files from the chunks'
        // CSS lists, so Vite's preload helper only preloads a route's JS chunks.
        modulePreload: {
            polyfill: false
        },

        rollupOptions: {
            input: inputs,
            output: {
                manualChunks(id) {
                    // The app and the code it uses (Rollup adds its dependencies).
                    // Route chunks import this chunk, never the start.js entry.
                    if (id === appModuleId) {
                        return 'app';
                    }
                    // ECharts loads only with the report pages.
                    if (/[\\/]node_modules[\\/](echarts|zrender)[\\/]/.test(id)) {
                        return 'vendor-echarts';
                    }
                },
                chunkFileNames: (chunkInfo) => {
                    const facadeModuleId = chunkInfo.facadeModuleId || '';
                    if (facadeModuleId.includes('resources/')) {
                        const srcPath = facadeModuleId.split('resources/').pop();
                        const dirPath = path.dirname(srcPath);
                        const mappedDir = mapOutputPath(dirPath + '/x').replace('/x', '');
                        return `${mappedDir}/[name].js`;
                    }
                    // Vendor and other shared chunks go into admin/js/
                    return "admin/js/[name].js";
                },
                entryFileNames: (chunkInfo) => {
                    const facadeModuleId = chunkInfo.facadeModuleId || '';
                    if (facadeModuleId.includes('resources/')) {
                        const srcPath = facadeModuleId.split('resources/').pop();
                        const baseName = path.basename(srcPath, path.extname(srcPath));
                        const dirPath = path.dirname(srcPath);
                        const defaultPath = `${dirPath}/${baseName}.js`;
                        return mapOutputPath(defaultPath);
                    }
                    return "[name].js";
                },
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.name || '';

                    if (name.endsWith('.css')) {
                        const originalFileName = (assetInfo.originalFileName || '').replace(/\\/g, '/');

                        for (const [sourcePath, outputPath] of Object.entries(legacyCssOutputMap)) {
                            if (originalFileName.endsWith(sourcePath)) {
                                return outputPath;
                            }
                        }

                        if (originalFileName.includes('resources/')) {
                            const srcPath = originalFileName.split('resources/').pop();
                            const dirPath = path.dirname(srcPath);
                            const baseName = path.basename(srcPath, path.extname(srcPath));
                            return `${dirPath}/${baseName}.css`;
                        }
                        return "admin/css/[name][extname]";
                    }

                    return "[name][extname]";
                },
            },
            plugins: [chunkCacheBustPlugin, verifyChunkImportsPlugin],
        },
    },

    resolve: {
        alias: {
            'vue': "vue/dist/vue.esm-bundler.js",
            '@': path.resolve(__dirname, "resources/"),
            '~element-plus': path.resolve(__dirname, 'node_modules/element-plus')
        },
        extensions: ['.mjs', '.js', '.ts', '.jsx', '.tsx', '.json', '.vue', '.scss']
    },

    server: {
        port: serverConfig.port,
        strictPort: serverConfig.strict_port,
        origin: `http://localhost:${serverConfig.port}`,
        hmr: {
            host: serverConfig.host,
            port: serverConfig.port,
            protocol: serverConfig.vite_protocol,
        },
        cors: {
            origin: true,
            credentials: true,
        },
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers': 'X-Requested-With, content-type, Authorization'
        }
    },

    optimizeDeps: {
        include: ["vue", "vue-router"]
    },

    esbuild: {
        drop: ['console', 'debugger'],
    },
});
