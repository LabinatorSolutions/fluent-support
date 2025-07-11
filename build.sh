#!/bin/bash

# Function to handle copying and compressing
copy_and_compress() {
  local source_dir="$1"
  local destination_dir="$2"
  local version="$3"
  local copy_list=("${@:4}")

  # Delete existing files in the destination directory
  rm -rf "$destination_dir"

  # Ensure the destination directory exists
  mkdir -p "$destination_dir"

  # Copy selected folders and files
  for item in "${copy_list[@]}"; do
    source_path="$source_dir/$item"
    destination_path="$destination_dir/$item"

    if [ -e "$source_path" ]; then
      cp -r "$source_path" "$destination_path"
      echo "Copied: $item"
    else
      echo "Warning: $item does not exist in the source directory."
    fi
  done

  echo -e "\nCopy completed."

  # Create index.php files with "Silence Is Golden" comment in assets folder and its subdirectories
  if [ -d "$destination_dir/assets" ]; then
    echo "Creating index.php files in assets directory and subdirectories..."
    create_index_files "$destination_dir/assets"
  fi

  # Run the zip command and suppress output
  cd "$destination_dir"
  cd ..
  local dest_dir_basename=$(basename "$destination_dir")

  # Use version in zip filename if provided
  if [ -n "$version" ]; then
    zip -rq "${dest_dir_basename}-${version}.zip" "$dest_dir_basename" -x "*.DS_Store"
    echo -e "\nCompressing Completed. builds/${dest_dir_basename}-${version}.zip is ready. Check the builds directory. Thanks!\n"
  else
    zip -rq "${dest_dir_basename}.zip" "$dest_dir_basename" -x "*.DS_Store"
    echo -e "\nCompressing Completed. builds/${dest_dir_basename}.zip is ready. Check the builds directory. Thanks!\n"
  fi

  cd .. # go back to plugin directory

  # Check for errors
  if [ $? -ne 0 ]; then
    echo "Error occurred while compressing."
    exit 1
  fi

  # Print completion message
  echo -e "\nCompressing Completed. builds/$(basename "$destination_dir").zip is ready. Check the builds directory. Thanks!\n"
}

# Function to create index.php files recursively
create_index_files() {
  local dir="$1"
  local index_content="<?php\n\n// Silence Is Golden."

  # Create index.php in the current directory
  echo "$index_content" > "$dir/index.php"
  echo "Created index.php in $dir"

  # Find all subdirectories and create index.php in each
  find "$dir" -type d -not -path "*/\.*" | while read subdir; do
    if [ "$subdir" != "$dir" ]; then
      echo "$index_content" > "$subdir/index.php"
      echo "Created index.php in $subdir"
    fi
  done
}

# Function to extract version from PHP file
extract_version() {
  local file="$1"
  local version=$(grep -o "Version: [0-9.]*" "$file" | cut -d' ' -f2)
  if [ -z "$version" ]; then
    version=$(grep -o "define.*FLUENTSUPPORTPRO_PLUGIN_VERSION.*'[0-9.]*'" "$file" | grep -o "'[0-9.]*'" | tr -d "'")
  fi
  echo "$version"
}

# Get args from command line
nodeBuild=true
withPro=false

for arg in "$@"; do
  case "$arg" in
    "--node-build")
      nodeBuild=true
      ;;
    "--with-pro")
      withPro=true
      ;;
  esac
done

if "$nodeBuild"; then
  echo -e "\nBuilding Main App\n"
  npx mix --production
  echo -e "\nBuild Completed"
fi

# Run composer dump-autoload with classmap-authoritative option for main plugin
echo -e "\nRunning composer dump-autoload for fluent-support\n"
cd "plugins/fluent-support"
composer dump-autoload --classmap-authoritative
cd -
echo -e "\nComposer dump-autoload for fluent-support completed\n"

# Copy and compress Fluent Support (without version in filename)
copy_and_compress "." "builds/fluent-support" "" "app" "assets" "boot" "config" "database" "language" "vendor" "composer.json" "fluent-support.php" "index.php" "readme.txt"

if ! "$withPro"; then
  exit 0
fi

echo -e "\nReadying Pro Addon\n"

# Extract version from pro plugin file
pro_version=$(extract_version "../fluent-support-pro/fluent-support-pro.php")
if [ -z "$pro_version" ]; then
  pro_version=$(extract_version "../fluent-support-pro/fluent-support-pro-boot.php")
fi
echo "Building Pro version: $pro_version"

# Run composer dump-autoload for Pro addon
echo -e "\nRunning composer dump-autoload for Pro addon\n"
cd "../fluent-support-pro"
composer dump-autoload --classmap-authoritative
cd -
echo -e "\nComposer dump-autoload for Pro addon completed\n"


# Copy and compress Fluent Support Pro with version number
copy_and_compress "../fluent-support-pro" "builds/fluent-support-pro" "$pro_version" "app" "languages" "database" "fluent-support-pro.php" "fluent-support-pro-boot.php" "index.php" "readme.txt"
