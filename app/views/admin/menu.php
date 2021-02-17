<div id='alpha_app'>
    <div class="fluent_framework-app">
        <div class="fluent_framework-main-menu-items">
            <div class="fluent_framework_menu_logo_holder">
                <a href="<?php echo $base_url; ?>">
                    <img src="<?php echo $logo; ?>" />
                    <?php if(defined('FLUENT_SUPPORT_VERSION')): ?>
                        <span>Pro</span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="fluent_framework_handheld"><span class="dashicons dashicons-menu-alt3"></span></div>
            <ul class="fluent_framework_menu">
                <?php foreach ($menuItems as $item): ?>
                    <?php $hasSubMenu = !empty($item['sub_items']); ?>
                    <li data-key="<?php echo $item['key']; ?>" class="fluent_framework_menu_item <?php echo ($hasSubMenu) ? 'fluent_framework_has_sub_items' : ''; ?> fluent_framework_item_<?php echo $item['key']; ?>">
                        <a class="fluent_framework_menu_primary" href="<?php echo $item['permalink']; ?>">
                            <?php echo $item['label']; ?>
                            <?php if($hasSubMenu){ ?>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <?php } ?></a>
                        <?php if($hasSubMenu): ?>
                            <div class="fluent_framework_submenu_items">
                                <?php foreach ($item['sub_items'] as $sub_item): ?>
                                    <a href="<?php echo $sub_item['permalink']; ?>"><?php echo $sub_item['label']; ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="fluent_framework-body">
            <router-view></router-view>
        </div>
    </div>
</div>
