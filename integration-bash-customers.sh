#!/bin/bash
SECONDS=0
echo "JOBS start " | tee /var/www/idosell.samba.win/public_html/logs/integration-log-customers.txt

actions() {
	/usr/bin/php /var/www/idosell.samba.win/public_html/yii xml-generator/generate-customers | tee -a /var/www/idosell.samba.win/public_html/logs/integration-log-customers.txt
	if (($SECONDS > 500)); then
	    break
	fi
}

while (($SECONDS <= 500)); do
   actions # Loop execution
done
echo "It takes $SECONDS seconds to complete this task..."  | tee -a /var/www/idosell.samba.win/public_html/logs/integration-log-customers.txt
