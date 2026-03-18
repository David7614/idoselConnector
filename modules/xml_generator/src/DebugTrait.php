<?php
namespace app\modules\xml_generator\src;

use app\models\AppConfig;

trait DebugTrait
{
    private function isDebug(): bool
    {
        return AppConfig::getValue(AppConfig::DISPLAY_DEBUG) == 1;
    }

    private function debugChannel(): string
    {
        $class = get_class($this);
        return substr(strrchr($class, '\\'), 1) ?: $class;
    }

    private function debug(string $msg): void
    {
        if ($this->isDebug()) {
            echo '[' . $this->debugChannel() . '] ' . $msg . PHP_EOL;
        }
    }

    private function debugDump($var, string $label = ''): void
    {
        if ($this->isDebug()) {
            if ($label) {
                echo '[' . $this->debugChannel() . '] ' . $label . ':' . PHP_EOL;
            }
            var_dump($var);
        }
    }

    private function debugPrint($var, string $label = ''): void
    {
        if ($this->isDebug()) {
            if ($label) {
                echo '[' . $this->debugChannel() . '] ' . $label . ':' . PHP_EOL;
            }
            print_r($var);
            echo PHP_EOL;
        }
    }
}
