#!/bin/bash
SECONDS=0
echo "JOBS start " | tee /var/www/vhosts/sambaprod.m2itsolutions.pl/logs/integration-url-fixer-log.txt
actionsall() {
	/usr/bin/php /var/www/vhosts/sambaprod.m2itsolutions.pl/yii xml-generator/url-fixer | tee -a /var/www/vhosts/sambaprod.m2itsolutions.pl/logs/integration-url-fixer-log.txt
}

actions() {
	/usr/bin/php /var/www/vhosts/sambaprod.m2itsolutions.pl/yii xml-generator/url-fixer | tee -a /var/www/vhosts/sambaprod.m2itsolutions.pl/logs/integration-url-fixer-log.txt
	if (($SECONDS > 500)); then
	    break
	fi
	
}
actionsall
while (($SECONDS <= 500)); do
   actions # Loop execution
done
echo "It takes $SECONDS seconds to complete this task..."  | tee -a /var/www/vhosts/sambaprod.m2itsolutions.pl/logs/integration-url-fixer-log.txt
