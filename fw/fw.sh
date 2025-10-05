#!/bin/bash

# User-defined variables
#SERVER_IP="localhost.com" or "1.1.1.1" needed for webpage link.
SERVER_IP="www.localhost.com"
SERVER_PORT="80"
#OUTPUT_PATH="/var/www/html/fw" etc. specify the web folder containing firmware files
OUTPUT_PATH="/var/www/html/fw"
#LOCATION_PATH="/var/www/html/fw" etc. Extract only 'fw' from the web folder containing firmware files and use it in the script. Do not modify.
LOCATION_PATH=${OUTPUT_PATH#/var/www/html/}
#FW_HOST="1.1.1.1" is the actual server IP for the AP's cli command (can be a URL, but AP must have DNS IP set).
FW_HOST="1.1.1.1"

# Define HTML Header and CSS Styles
HEADER_AND_CSS='
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmware Update Commands</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 20px;
        }
        .command-block {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d9534f;
            text-align: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
        }
        h2 {
            color: #333;
            margin-top: 0;
            margin-bottom: 10px;
        }
        pre {
            background-color: #e9e9e9;
            padding: 10px;
            border-radius: 5px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .menu-link {
            display: inline-block;
            margin: 5px 0;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            transition: background-color 0.3s;
        }
        .menu-link:hover {
            background-color: #e2e6ea;
        }
    </style>
</head>
<body>
'

# Define HTML Footer
FOOTER='
</body>
</html>
'

# Create output directory (if it does not exist)
mkdir -p "$OUTPUT_PATH"

# Group files by version from their names, and save the version prefix and description
# Save standalone and Unleashed files separately
declare -A standalone_versions
declare -A unleashed_versions

for file in *.bl7; do
    [ -f "$file" ] || continue
    
    filename=$(basename "$file" .bl7)
    version_full=$(echo "$filename" | cut -d'_' -f2)

    version_prefix=""
    version_desc=""

    if [[ "$version_full" =~ ^1[0-9]{2} ]]; then
        version_prefix="v11x"
        version_desc="Standalone firmware (11x.x version)"
        standalone_versions["$version_prefix"]="$version_desc"
    elif [[ "$version_full" =~ ^200\.([0-9]+) ]]; then
        version_prefix="v200${BASH_REMATCH[1]}"
        version_desc="Unleashed firmware (200.${BASH_REMATCH[1]}.x version)"
        unleashed_versions["$version_prefix"]="$version_desc"
    fi
done

# Dynamically generate index.html file content
INDEX_CONTENT=$(cat << EOF
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AP Firmware Upgrade Guide</title>
<style>
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    line-height: 1.6;
    margin: 0;
    padding: 0;
    background-color: #f0f2f5;
    color: #333;
  }
  .container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px 40px;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
  }
  header {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 3px solid #007bff;
    margin-bottom: 30px;
  }
  h1 {
	color: #d9534f;
	text-align: center;
	border-bottom: 2px solid #eee;
	padding-bottom: 10px;
	margin-top: 0;
 }
  header h1 {
            color: #d9534f;
            text-align: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
  }
  header p {
    font-size: 1.1em;
    color: #7f8c8d;
    margin-top: 10px;
  }
  .section {
    margin-bottom: 30px;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
  }
  .section h2 {
    font-size: 1.8em;
    color: #34495e;
    margin-top: 0;
    margin-bottom: 15px;
    border-bottom: 2px solid #bdc3c7;
    padding-bottom: 5px;
  }
  .section ul {
    list-style-type: none;
    padding: 0;
    margin: 0;
  }
  .section ul li {
    margin-bottom: 15px;
  }
  .section a {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
    transition: color 0.3s ease;
  }
  .section a:hover {
    color: #0056b3;
    text-decoration: underline;
  }
  .note {
    font-size: 1.1em;
    font-weight: 600;
    color: #e74c3c;
    text-align: center;
    margin-top: 20px;
  }
  .menu-link {
    display: inline-block;
    margin: 5px 0;
    padding: 8px 15px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    text-decoration: none;
    color: #007bff;
    transition: background-color 0.3s;
  }
		
  .menu-link:hover {
    background-color: #e2e6ea;
  }

  code {
    background-color: #e8f5e9;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', Courier, monospace;
    color: #27ae60;
  }
</style>
</head>
<body>

<div class="container">
    <a align=left href="../portal/index.php" class="menu-link">☎ HOME</a><p>
    <h1>AP Firmware Upgrade</h1>
    <p>Connect to the AP via SSH and insert the script for your model.</p>
    <p>☞ After downloading the firmware, reboot with the 'reboot' command, or re-apply power.</p>
    <hr>
EOF
)

# Add standalone firmware first (no duplicates)
for prefix in "${!standalone_versions[@]}"; do
    description="${standalone_versions[$prefix]}"
    INDEX_CONTENT+="<div class=\"section\"><h2>${description}</h2><p>${description} upgrade script links.</p><ul><li><a href=\"http://$SERVER_IP:${SERVER_PORT}/${LOCATION_PATH}/${prefix}.html\">${description} (for RCKS file)</a></li><li><a href=\"http://$SERVER_IP:${SERVER_PORT}/${LOCATION_PATH}/${prefix}_bl7.html\">${description} (for BL7 file)</a></li></ul></div>"
done

# Add Unleashed firmware in sorted order
sorted_keys=$(printf '%s\n' "${!unleashed_versions[@]}" | sort -rV)

for prefix in $sorted_keys; do
    description="${unleashed_versions[$prefix]}"
    INDEX_CONTENT+="<div class=\"section\"><h2>${description}</h2><p>${description} upgrade script links.</p><ul><li><a href=\"http://$SERVER_IP:${SERVER_PORT}/${LOCATION_PATH}/${prefix}.html\">${description} (for RCKS file)</a></li><li><a href=\"http://$SERVER_IP:${SERVER_PORT}/${LOCATION_PATH}/${prefix}_bl7.html\">${description} (for BL7 file)</a></li></ul></div>"
done

INDEX_CONTENT+="</div></body></html>"

echo "$INDEX_CONTENT" > "$OUTPUT_PATH/index.html"

# Initialize and create version-specific HTML files
# This part is unchanged. Initialize files for all versions.
declare -A all_versions
for file in *.bl7; do
    [ -f "$file" ] || continue
    filename=$(basename "$file" .bl7)
    version_full=$(echo "$filename" | cut -d'_' -f2)
    if [[ "$version_full" =~ ^1[0-9]{2} ]]; then
        version_prefix="v11x"
        version_desc="Standalone firmware (11x.x version)"
    elif [[ "$version_full" =~ ^200\.([0-9]+) ]]; then
        version_prefix="v200${BASH_REMATCH[1]}"
        version_desc="Unleashed firmware (200.${BASH_REMATCH[1]}.x version)"
    else
        continue
    fi
    all_versions["$version_prefix"]="$version_desc"
done

for prefix in "${!all_versions[@]}"; do
    description="${all_versions[$prefix]}"
    echo "$HEADER_AND_CSS<h1>${description}</h1>" > "$OUTPUT_PATH/${prefix}.html"
    echo "$HEADER_AND_CSS<h1>${description} - BL7</h1>" > "$OUTPUT_PATH/${prefix}_bl7.html"
    echo "$FOOTER" >> "$OUTPUT_PATH/${prefix}.html"
    echo "$FOOTER" >> "$OUTPUT_PATH/${prefix}_bl7.html"
done

# Iterate over bl7 files in the current directory
for file in *.bl7; do
    [ -f "$file" ] || continue

    filename=$(basename "$file" .bl7)
    model_name=$(echo "$filename" | cut -d'_' -f1)
    version_full=$(echo "$filename" | cut -d'_' -f2)

    version_prefix=""

    if [[ "$version_full" =~ ^1[0-9]{2} ]]; then
        version_prefix="v11x"
    elif [[ "$version_full" =~ ^200\.([0-9]+) ]]; then
        version_prefix="v200${BASH_REMATCH[1]}"
    else
        continue
    fi

    # Create rcks file content and write to file
    rcks_content="[rcks_fw.main]\n0.0.0.0\n"
    rcks_content+="${LOCATION_PATH}/$file\n"
    rcks_content+=$(stat -c %s "$file")
    rcks_path="$OUTPUT_PATH/${model_name}_${version_prefix}_cntrl.rcks"
    echo -e "$rcks_content" > "$rcks_path"

    # Create HTML file content
    html_content="\n<div class=\"command-block\">"
    html_content+="<h2>Model: $model_name</h2>"
    html_content+="<pre>"
    html_content+="-------------------------------------------------------\n"
    html_content+="$model_name\n"
    html_content+="-------------------------------------------------------\n"
    html_content+="fw set proto http\n"
    html_content+="fw set port ${SERVER_PORT}\n"
    html_content+="fw set host ${FW_HOST}\n"
    html_content+="fw set control ${LOCATION_PATH}/${model_name}_${version_prefix}_cntrl.rcks\n"
    html_content+="fw update\n"
    html_content+="set factory\n"
    html_content+="-------------------------------------------------------\n"
    html_content+="</pre></div>"

    # Create bl7 HTML file content
    bl7_html_content="\n<div class=\"command-block\">"
    bl7_html_content+="<h2>Model: $model_name</h2>"
    bl7_html_content+="<pre>"
    bl7_html_content+="-------------------------------------------------------\n"
    bl7_html_content+="$model_name\n"
    bl7_html_content+="-------------------------------------------------------\n"
    bl7_html_content+="fw set proto http\n"
    bl7_html_content+="fw set port ${SERVER_PORT}\n"
    bl7_html_content+="fw set host ${FW_HOST}\n"
    bl7_html_content+="fw set control ${LOCATION_PATH}/${file}\n"
    bl7_html_content+="fw update\n"
    bl7_html_content+="set factory\n"
    bl7_html_content+="-------------------------------------------------------\n"
    bl7_html_content+="</pre></div>"

    # Append content to file
    echo -e "$html_content" >> "$OUTPUT_PATH/${version_prefix}.html"
    echo -e "$bl7_html_content" >> "$OUTPUT_PATH/${version_prefix}_bl7.html"
    
    echo "Created: $rcks_path"
done

echo "HTML and RCKs files have been successfully generated."
