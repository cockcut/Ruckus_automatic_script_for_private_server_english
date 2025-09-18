#!/bin/bash

# Install necessary programs
sudo dnf -y install wget httpd php php-json php-curl jq php-snmp net-snmp-utils php-mbstring php-xml expect php-fpm git


# Grant execute permissions to *.sh scripts in the current directory and subdirectories
find . -name "*.sh" -exec chmod +x {} \;
# Grant execute permissions to directories
chmod -R 777 api-u
chmod 777 script/uploads
chmod 777 -R upgrade
chmod 777 -R supported

# Related to oui script below
# Dynamically get the actual path of the ouiget.sh script.
# 'readlink -f' finds the absolute path of the script, including symbolic links.
# 'dirname' extracts only the directory part from that absolute path.
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
OUIGET_SCRIPT="$SCRIPT_DIR/ouiget.sh"

# Grant execute permissions to ouiget.sh
chmod +x "$OUIGET_SCRIPT"

# Define job to add to crontab (to be executed at 00:00 every day)
CRON_JOB="0 0 * * * $OUIGET_SCRIPT"

# Get the current crontab list
(crontab -l 2>/dev/null) > my_crontab_jobs

# Check if the job already exists
if ! grep -qF -- "$CRON_JOB" my_crontab_jobs; then
  # If the job does not exist, add it
  echo "$CRON_JOB" >> my_crontab_jobs

  # Install the new crontab list
  crontab my_crontab_jobs
  echo "ouiget.sh has been successfully added to crontab."
else
  echo "ouiget.sh is already added to crontab. Skipping."
fi

# Delete temporary file
rm my_crontab_jobs

# Increase file upload capacity in php.ini
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 100M/' /etc/php.ini
sudo sed -i 's/^post_max_size = .*/post_max_size = 100M/' /etc/php.ini
sudo sed -i 's~^;upload_tmp_dir *=.*~upload_tmp_dir = /tmp~' /etc/php.ini

sudo sed -i 's/^post_max_size = .*/post_max_size = 100M/' /etc/php.ini

# Turn on httpd service
sudo systemctl enable httpd
sudo systemctl restart httpd
sudo systemctl enable php-fpm
sudo systemctl restart php-fpm

# Open firewall
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload

# --- Allow SElinux ---
setenforce 0
# --- SELINUX=enforcing 일 경우에만 SELINUX=permissive로 변경 ---
sudo sed -i '/^SELINUX=enforcing/s/enforcing/permissive/' /etc/selinux/config
