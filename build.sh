#!/bin/bash

# Function to handle copying and compressing
copy_and_compress() {
  local source_dir="$1"
  local destination_dir="$2"
  local copy_list=("${@:3}")

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

  # Run the zip command and suppress output

    #echo "Current working directory : $(pwd)"
    #// now got to destination_dir and zip it
    cd "$destination_dir"
    cd ..
    #local build_dir_basename=$(basename "`pwd`")
    local dest_dir_basename=$(basename "$destination_dir")

    #echo "Current working directory : $(basename "$current_dir_basename")"
    #echo "Current working directory : $(pwd)"
    zip -rq "${dest_dir_basename}.zip" "$dest_dir_basename" -x "*.DS_Store"

    cd .. # go back to fluent-crm plugin directory



  # Check for errors
  if [ $? -ne 0 ]; then
    echo "Error occurred while compressing."
    exit 1
  fi

  # Print completion message
  echo -e "\nCompressing Completed. builds/$(basename "$destination_dir").zip is ready. Check the builds directory. Thanks!\n"
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

# Copy and compress Fluent CRM
copy_and_compress "." "builds/fluent-support" "app" "assets" "boot" "config" "database" "language" "vendor" "composer.json" "fluent-support.php" "index.php" "readme.txt"

if ! "$withPro"; then
  exit 0
fi

echo -e "\nReadying Pro Addon\n"

# Copy and compress Fluent Campaign Pro
copy_and_compress "../fluent-support-pro" "builds/fluent-support-pro" "app" "languages" "database" "fluent-support-pro.php" "fluent-support-pro-boot.php" "index.php" "readme.txt"
