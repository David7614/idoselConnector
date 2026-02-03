#!/bin/bash
SECONDS=0
echo "JOBS start " | tee /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
actionsall() {
	/usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-phonesubscribers | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
}

actions() {
	/usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-phonesubscribers | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
	if (($SECONDS > 500)); then
	    break
	fi
}
actionsall
while (($SECONDS <= 500)); do
   actions # Loop execution
done
echo "It takes $SECONDS seconds to complete this task..."  | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
