Integracja idosell i shopera

W razie kłopotów:
https://sambaprod.m2itsolutions.pl/adminer.php
Cała integracja opiera się o xml_feed_queue. Znajdujemy id usera w users na podstawie zgoszenia i patrzymy jego kolejkę filtrując po current_integrate_user
Kolumna integrated to status:
0 - czeka na wykonanie
1 - trwa
2 - zakończone
99 - błąd ( zapisuje się do parameters )
Z reguły wystarczy tam coś pchnąc, przestawić datę etc żeby działało.

Weryfikacja integracji usera to z reguł zmaczowanie tabelek user i accesstokens.



------------ ZADANIA CRON
* * * * * /usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/generate-countries >/dev/null 2>&1

1 23 * * * /usr/bin/php /home/yii/sambaprod.m2itsolutions.pl/yii xml-generator/prepare-queue >/dev/null 2>&1

* * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash-orders.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash-customers.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash-products.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash-shoper-products.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash-subscribers.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/sambaprod.m2itsolutions.pl/integration-bash-shoper-subscribers.sh >/dev/null 2>&1

