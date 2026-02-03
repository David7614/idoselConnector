#!/bin/bash
SECONDS=0
echo "JOBS start " | tee /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
actionsall() {
	/usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-categories | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
	/usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-tags | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
}

actions() {
    /usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-categories | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
   if (($SECONDS > 50)); then
	    break
	fi
	/usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-tags | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
}
actionsall
while (($SECONDS <= 50)); do
   actions # Loop execution
done
echo "It takes $SECONDS seconds to complete this task..."  | tee -a /home/yii/sambaprod.m2itsolutions.pl/logs/integration-log.txt
