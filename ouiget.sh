#!/bin/bash

# Dynamically get the absolute path of the ouiget.sh script and store it in a variable.
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# First, delete any remaining OUI files.
rm -f "$SCRIPT_DIR/oui/index.html"*

# Save the OUI to the directory.
wget https://standards-oui.ieee.org/ -P "$SCRIPT_DIR/oui/"

# Read the content of the OUI file and filter lines that contain "(hex)".
cat "$SCRIPT_DIR/oui/index.html" | \
grep hex | \

# To facilitate parsing, change the delimiter "(hex)" to "|".
sed 's/(hex)/|/g' | \

# Use awk to separate columns by the "|" delimiter and format the data.
awk -F '|' -v timestamp="$(date +"%Y/%m/%d/%H:%M")" '

# At the start, print the header and timestamp.
  BEGIN {
    print "*" timestamp " Update\n"
#    print "OUI\t\tOUI(:)\t\tOUI(lower)\t\tOUI(:lower)\t\t\t\tVender"
    print "OUI\t\tOUI(:)\t\tOUI(lower)\tOUI(:lower)\t\t\tVender"
    print "------------------------------------------------------------------------------------------------------------------------------------------"
  }

# Process each line.
  {
# Store the original OUI value in a variable.
    original_oui = $1
# Create the OUI value with colons.
    colon_oui = original_oui
    gsub("-", ":", colon_oui)
# Create the lowercase OUI value.
    lower_oui = tolower(original_oui)
# Convert the OUI value with colons to lowercase.
    lower_colon_oui = tolower(colon_oui)
    
# Print a total of 5 columns.
#    printf "%s\t\t%s\t\t%s\t\t%s\t\t%s\n", original_oui, colon_oui, lower_oui, lower_colon_oui, $2
    printf "%s\t%s\t%s\t%s\t%s\n", original_oui, colon_oui, lower_oui, lower_colon_oui, $2
  }
# At the end, print the footer.
  END {
    print "------------------------------------------------------------------------------------------------------------------------------------------\nEND"
  }
' > $SCRIPT_DIR/oui/oui.txt
rm -f $SCRIPT_DIR/oui/index.html*
