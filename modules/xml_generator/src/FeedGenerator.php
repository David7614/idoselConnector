<?php
namespace app\modules\xml_generator\src;

interface FeedGenerator
{
    public function generate();
    public function getFile();
}