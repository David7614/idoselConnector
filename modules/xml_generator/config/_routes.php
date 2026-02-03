<?php

return [
    'xml/<uuid:\w+>/products.xml' => 'xml_generator/products/generate',
    'xml/<uuid:\w+>/customers.xml' => 'xml_generator/customers/generate',
    'xml/<uuid:\w+>/categories.xml' => 'xml_generator/categories/generate',
    'xml/<uuid:\w+>/orders.xml' => 'xml_generator/orders/generate'
];