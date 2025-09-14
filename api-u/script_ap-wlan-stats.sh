#UAP=$1
#UAP_USERNAME=$2
#UAP_PASSWORD=$3
UAP=$1
UAP_USERNAME=$2
UAP_PASSWORD=$3

mkdir ./$UAP

# create a cookie jar
UAP_COOKIE=$(mktemp)

# sniff login url
UAP_LOGIN_URL="$(curl https://$UAP -k -s -L -I -o /dev/null -w '%{url_effective}')"
# login and collect token and session cookie
UAP_XSS="$(curl $UAP_LOGIN_URL -k -s -c $UAP_COOKIE  -d username=$UAP_USERNAME -d password=$UAP_PASSWORD -d ok=Log\ In -i | awk '/^HTTP_X_CSRF_TOKEN:/ { print $2 }' | tr -d '\040\011\012\015')"
UAP_CONF="$(dirname $UAP_LOGIN_URL)/_cmdstat.jsp"

# calculate the interval start and end time
INTERVAL_STOP=$(date +%s)
INTERVAL_START=$(date -d '24 hours ago' +%s)

# get ap stats for the calculated interval
curl $UAP_CONF -H "X-CSRF-Token: $UAP_XSS" -k -s -b $UAP_COOKIE -c $UAP_COOKIE --data "<ajax-request action=\"getstat\" comp=\"stamgr\" enable-gzip=\"0\" caller=\"SCI\"><ap INTERVAL-STATS=\"yes\" INTERVAL-START=\"$INTERVAL_START\" INTERVAL-STOP=\"$INTERVAL_STOP\" LEVEL=\"1\"/></ajax-request>" > ./$UAP/ap-result.xml

# get wlan stats for the calculated interval
curl $UAP_CONF -H "X-CSRF-Token: $UAP_XSS" -k -s -b $UAP_COOKIE -c $UAP_COOKIE --data "<ajax-request action=\"getstat\" comp=\"stamgr\" enable-gzip=\"0\" caller=\"SCI\"><vap INTERVAL-STATS=\"yes\" INTERVAL-START=\"$INTERVAL_START\" INTERVAL-STOP=\"$INTERVAL_STOP\" LEVEL=\"1\"/></ajax-request>" > ./$UAP/wlan-result.xml

# remove cookie jar
rm $UAP_COOKIE

# create download folder
mkdir ./$UAP/download

# XML file name
ap_XML_FILE="./$UAP/ap-result.xml"

wlan_XML_FILE="./$UAP/wlan-result.xml"

printf '\n< AP Information List>\n'
# Header to be printed
{
echo "mac $|$ ap-name $|$ model $|$ ip $|$ netmask $|$ gateway $|$ serial-number $|$ firmware-version $|$ num-sta $|$ eth0 $|$ eth1 $|$ 2G_ch $|$ 5G_ch $|$ 6G_ch"

echo "----- $|$ ----- $|$ -----  $|$ -----  $|$ -----  $|$ ----- $|$ -----  $|$ -----  $|$ -----  $|$ -----  $|$ ----- $|$ ----- $|$ ----- $|$ -----"

# Get the list of all AP's MAC addresses
mac_addresses=$(xmllint --xpath "//ap/@mac" "$ap_XML_FILE" 2>/dev/null | sed 's/mac="\([^"]*\)"/\1\n/g')

# Extract information for each AP
for mac in $mac_addresses; do
    # Extract each AP's information individually from XML
    ap_name=$(xmllint --xpath "string(//ap[@mac='$mac']/@ap-name)" "$ap_XML_FILE" 2>/dev/null)
    model=$(xmllint --xpath "string(//ap[@mac='$mac']/@model)" "$ap_XML_FILE" 2>/dev/null)
    ip=$(xmllint --xpath "string(//ap[@mac='$mac']/@ip)" "$ap_XML_FILE" 2>/dev/null)
    netmask=$(xmllint --xpath "string(//ap[@mac='$mac']/@netmask)" "$ap_XML_FILE" 2>/dev/null)
    gateway=$(xmllint --xpath "string(//ap[@mac='$mac']/@gateway)" "$ap_XML_FILE" 2>/dev/null)
    serial_number=$(xmllint --xpath "string(//ap[@mac='$mac']/@serial-number)" "$ap_XML_FILE" 2>/dev/null)
    firmware_version=$(xmllint --xpath "string(//ap[@mac='$mac']/@firmware-version)" "$ap_XML_FILE" 2>/dev/null)
    num_sta=$(xmllint --xpath "string(//ap[@mac='$mac']/@num-sta)" "$ap_XML_FILE" 2>/dev/null)

    # Extract eth0, eth1 Physical values (print NO if not found)
    eth0=$(xmllint --xpath "string(//ap[@mac='$mac']//lan-port[@Interface='eth0']/@Physical)" "$ap_XML_FILE" 2>/dev/null)
    eth0=${eth0:-NULL}
    eth1=$(xmllint --xpath "string(//ap[@mac='$mac']//lan-port[@Interface='eth1']/@Physical)" "$ap_XML_FILE" 2>/dev/null)
    eth1=${eth1:-NULL}

    # Extract 2G, 5G, 6G channel values (print NO if not found)
	# Original script
    #ch_2g=$(xmllint --xpath "string(//ap[@mac='$mac']//radio[@radio-band='2.4g']/@channel)" "$ap_XML_FILE" 2>/dev/null)
    #ch_2g=${ch_2g:-NULL}
    #ch_5g=$(xmllint --xpath "string(//ap[@mac='$mac']//radio[@radio-band='5g']/@channel)" "$ap_XML_FILE" 2>/dev/null)
    #ch_5g=${ch_5g:-NULL}
    #ch_6g=$(xmllint --xpath "string(//ap[@mac='$mac']//radio[@radio-band='6g']/@channel)" "$ap_XML_FILE" 2>/dev/null)
    #ch_6g=${ch_6g:-NULL}
	
	# Modified script
	ch_2g=$(xmllint --xpath "string(//ap[@mac='$mac']//radio[contains(@radio-band,'2.4g') or contains(@radio-type,'11g') or contains(@radio-type,'11ng')]/@channel)" "$ap_XML_FILE" 2>/dev/null)
	ch_2g=${ch_2g:-NULL}
	ch_5g=$(xmllint --xpath "string(//ap[@mac='$mac']//radio[contains(@radio-band,'5g') or contains(@radio-type,'11a')]/@channel)" "$ap_XML_FILE" 2>/dev/null)
	ch_5g=${ch_5g:-NULL}
	ch_6g=$(xmllint --xpath "string(//ap[@mac='$mac']//radio[@radio-band,'6g']/@channel)" "$ap_XML_FILE" 2>/dev/null)
	ch_6g=${ch_6g:-NULL}


    # Print AP information
    echo "$mac $|$ $ap_name $|$ $model $|$ $ip $|$ $netmask $|$ $gateway $|$ $serial_number $|$ $firmware_version $|$ $num_sta $|$ $eth0 $|$ $eth1 $|$ $ch_2g $|$ $ch_5g $|$ $ch_6g"
done 
} | column -t -s '$' | tee ./$UAP/1.ap.list

# Read XML data from result.xml file and extract each item.
bssid_list=$(xmllint --xpath "//vap/@bssid" $wlan_XML_FILE | sed 's/ /\n/g' | sed 's/bssid="//g' | sed 's/"//g')
ssid_list=$(xmllint --xpath "//vap/@ssid" $wlan_XML_FILE | sed 's/ /\n/g' | sed 's/ssid="//g' | sed 's/"//g')
radio_band_list=$(xmllint --xpath "//vap/@radio-band" $wlan_XML_FILE | sed 's/ /\n/g' | sed 's/radio-band="//g' | sed 's/"//g')
ap_list=$(xmllint --xpath "//vap/@ap" $wlan_XML_FILE | sed 's/ /\n/g' | sed 's/ap="//g' | sed 's/"//g')
#radio_type_list=$(xmllint --xpath "//vap/@radio-type" $wlan_XML_FILE | sed 's/ /\n/g' | sed 's/radio-type="//g' | sed 's/"//g')
radio_type_list=$(xmllint --xpath "//vap/@ieee80211-radio-type" $wlan_XML_FILE | sed 's/ /\n/g' | sed 's/ieee80211-radio-type="//g' | sed 's/"//g')

# Convert each item to an array (separated by spaces)
IFS=$'\n' read -d '' -r -a bssid_array <<< "$bssid_list"
IFS=$'\n' read -d '' -r -a ssid_array <<< "$ssid_list"
IFS=$'\n' read -d '' -r -a radio_band_array <<< "$radio_band_list"
IFS=$'\n' read -d '' -r -a ap_array <<< "$ap_list"
IFS=$'\n' read -d '' -r -a radio_type_array <<< "$radio_type_list"


printf '\n\n---------------------------------------------------------------------------------------------------------------\n\n'
printf '\n< WLAN Information List>\n'

# Print WLAN information
{
# Print title
echo "BSSID $|$ SSID $|$ Radio $|$ AP_mac $|$ 802.11"
# Separator line
echo "----- $|$ ----- $|$ ----- $|$ ----- $|$ -----"
for i in "${!bssid_array[@]}"; do
    echo "${bssid_array[$i]} $|$ ${ssid_array[$i]} $|$ ${radio_band_array[$i]} $|$ ${ap_array[$i]} $|$ ${radio_type_array[$i]}"
done
} | column -t -s '$' | tee ./$UAP/2.wlan.list

# Copy list to download folder
cat "./$UAP/1.ap.list" | sed -e 's/|/,/g' > "./$UAP/download/$UAP.ap.csv"
cat "./$UAP/2.wlan.list" | sed -e 's/|/,/g' > "./$UAP/download/$UAP.wlan.csv"