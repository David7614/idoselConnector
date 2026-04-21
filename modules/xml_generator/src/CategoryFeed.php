<?php
namespace app\modules\xml_generator\src;

use app\modules\idosellv3\models\ApiClient;
use app\services\FeedStorageService;

class CategoryFeed extends XmlFeed
{
    /**
     * @param null $what
     * @return bool
     *
     */
    private $request_parameters = [];
    private $gate               = "/api/admin/v3/products/categories";
    private $_client;

    const API_RESULT_COUNT = 1000;

    public function generate($what = null): int
    {
        if ($this->_user->config->get('product_feed_disable') == 1) {
            throw new \app\services\FeedDisabledException('Product feed disabled');
        }
        if (! $this->_user->getApiKey()) {
            echo "no api key";
            return false;
        }

        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());

        if (FeedStorageService::isConfigured()) {
            return $this->generateViaStorage();
        }

        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if (! $this->isFinished()) {
            $created = $this->createOrAddCategoryTempXml($temp);
        } elseif (!file_exists($temp)) {
            echo "temp file missing - resetting xml phase" . PHP_EOL;
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            $created = $this->createOrAddCategoryTempXml($temp);
        } else {
            $created = $this->createCategoryXml($file, $temp);
        }

        return $created;
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return parent::getFile($get_file_path, $temp);
    }

    private function getStorageKey(bool $temp = false): string
    {
        $ext = $temp ? '.xml.tmp' : '.xml';
        return 'category/' . $this->_user->uuid . '/category' . $ext;
    }

    private function generateViaStorage(): int
    {
        $storage = FeedStorageService::create();
        $tempKey = $this->getStorageKey(true);
        $fileKey = $this->getStorageKey(false);

        if (!$this->isFinished()) {
            return $this->createOrAddCategoryTempXmlViaStorage($storage, $tempKey, $fileKey);
        } elseif (!$storage->chunksExist($tempKey)) {
            echo "temp chunks missing in storage - resetting xml phase" . PHP_EOL;
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            return $this->createOrAddCategoryTempXmlViaStorage($storage, $tempKey, $fileKey);
        } else {
            return $this->createCategoryXmlViaStorage($storage, $fileKey, $tempKey, (int) $this->_queue->page);
        }
    }

    private function createOrAddCategoryTempXmlViaStorage(FeedStorageService $storage, string $tempKey, string $fileKey): int
    {
        try {
            $this->checkQueueConstraints();
            $this->request_parameters['results_limit'] = self::API_RESULT_COUNT;
            $request                                   = $this->request_parameters;
            $request['returnProducts']                 = 'active';
            $request['resultsPage']                    = $this->_queue->page;
            $response                                  = $this->_client->get($this->gate, $request);

            if ($this->_queue->page >= $this->_queue->max_page) {
                echo "max page exceeded";
                return 10;
            }

            if (isset($response['errors']) && ! empty($response['errors']['faultString'])) {
                echo $response['errors']['faultString'];
                return false;
            }

            $categories_array = [];
            $replace_from     = ['/', ' ', '"', '″', ',', 'ą', 'ę', 'ź', 'ć', 'ż', 'ł', 'ó', 'ń'];
            $replace_to       = ['-', '-', '-', '-', '-', 'a', 'e', 'z', 'c', 'z', 'l', 'o', 'n'];

            foreach ($response['categories'] as $category) {
                $prepared_data = strtolower($category['lang_data'][0]['plural_name']);
                $prepared_data = str_replace($replace_from, $replace_to, $prepared_data);
                $prepared_data = str_replace('--', '-', $prepared_data);
                $url           = 'https://' . $this->_user->username . '/pl/categories/' . $prepared_data . '-' . $category['id'] . '.html';

                $categories_array[$category['id']] = [
                    'id'     => $category['id'],
                    'parent' => $category['parent_id'],
                    'TITLE'  => $category['lang_data'][0]['plural_name'],
                    'URL'    => $url,
                ];
            }

            print_r($categories_array);

            $catOrdered = $this->makeRecursive($categories_array);
            $buffer     = '';

            if (count($catOrdered) > 0) {
                $wrapper = new \SimpleXMLElement('<CATEGORIES/>');
                foreach ($catOrdered as $entry) {
                    $child = $wrapper->addChild('ITEM');
                    $child->addChild('TITLE', htmlspecialchars($entry['TITLE']));
                    $child->addChild('URL', htmlspecialchars($entry['URL']));
                    if (isset($entry['children']) && ! empty($entry['children'])) {
                        $child->addChild('ITEM', $this->makeXml($entry['children'], $child));
                    }
                    $xml = $child->asXml();
                    $xml = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $xml);
                    $buffer .= $xml;
                }
            }

            $chunkIndex = $this->_queue->page;

            if ($chunkIndex > 0 && !$storage->chunkExists($tempKey, $chunkIndex - 1)) {
                echo "chunk " . ($chunkIndex - 1) . " missing — resetting xml phase" . PHP_EOL;
                $storage->deleteChunks($tempKey);
                $this->_queue->page     = 0;
                $this->_queue->max_page = 0;
                $this->_queue->save();
                return 1;
            }

            $storage->putChunk($tempKey, $chunkIndex, $buffer);

            $this->_queue->increasePage();

            if ($this->_queue->page >= $this->_queue->max_page) {
                return $this->createCategoryXmlViaStorage($storage, $fileKey, $tempKey, (int) $this->_queue->page);
            }

            return true;

        } catch (\Exception $e) {
            echo $e->getMessage();
            $this->_queue->setErrorStatus($e->getMessage());
            die();
        }
    }

    private function createCategoryXmlViaStorage(FeedStorageService $storage, string $fileKey, string $tempKey, int $expectedChunks): int
    {
        echo "FINALIZING XML (storage) — expected chunks: {$expectedChunks}" . PHP_EOL;
        $content  = $storage->collectAndDeleteChunks($tempKey, $expectedChunks);
        $finalXml = '<?xml version="1.0" encoding="UTF-8"?><CATEGORIES>' . $content . '</CATEGORIES>';
        $storage->put($fileKey, $finalXml, 'application/xml');
        echo "XML uploaded to storage: " . $fileKey . PHP_EOL;
        return 10;
    }

    private function checkQueueConstraints()
    { // todo

        if ($this->_queue->max_page > 0) {
            return false; // no need every time
        }
        $request                  = $this->request_parameters;
        $request['results_limit'] = 1;
        $response                 = $this->_client->get($this->gate, $request);
        // var_dump($response);
        // die();
        if (empty($response) || !is_array($response)) {
            throw new \Exception('Gateway did not respond (checkQueueConstraints)');
        }
        if (! $response['results_number_all']) {
            echo "no results" . PHP_EOL;
            return false;
        }

        $maxPage = ceil($response['results_number_all'] / self::API_RESULT_COUNT);
        if ($this->_queue->max_page < $maxPage) {
            $this->_queue->max_page = $maxPage;
            $this->_queue->save();
        }

        return true;
    }

    public function createOrAddCategoryTempXml($temp)
    {
        touch($temp);
        try {
            $this->checkQueueConstraints();
            $this->request_parameters['results_limit'] = self::API_RESULT_COUNT;
            $request                                   = $this->request_parameters;
            $request['returnProducts']                 = 'active';

            $page                   = 0;
            $categories             = new \SimpleXMLElement('<CATEGORIES/>');
            $request['resultsPage'] = $this->_queue->page;
            // $request['params']['resultsPage'] = $this->_queue->page;
            $response = $this->_client->get($this->gate, $request);
            // var_dump($response); die;
            // $results_page = property_exists($response, 'resultsNumberPage') ? $response->resultsNumberPage : 1;
            // $this->_queue->setMaxPages($results_page);

            if ($this->_queue->page >= $this->_queue->max_page) {
                echo "max page exceded";
                return 10;
            }
            // print_r($response->categories); die;
            if (isset($response['errors']) && ! empty($response['errors']['faultString'])) {
                echo $response['errors']['faultString'];
                return false;
            }
            $categories_array = [];

            $i            = 0;
            $replace_from = ['/', ' ', '”', '″', ',', 'ą', 'ę', 'ź', 'ć', 'ż', 'ł', 'ó', 'ń'];
            $replace_to   = ['-', '-', '-', '-', '-', 'a', 'e', 'z', 'c', 'z', 'l', 'o', 'n'];

            foreach ($response['categories'] as $category) {

                $prepared_data = strtolower($category['lang_data'][0]['plural_name']);
                $prepared_data = str_replace($replace_from, $replace_to, $prepared_data);
                $prepared_data = str_replace('--', '-', $prepared_data);

                $url = 'https://' . $this->_user->username . '/pl/categories/' . $prepared_data . '-' . $category['id'] . '.html';

                $categories_array[$category['id']] = [
                    'id'     => $category['id'],
                    'parent' => $category['parent_id'],
                    'TITLE'  => $category['lang_data'][0]['plural_name'],
                    'URL'    => $url,
                ];
                $i++;
            }

            print_r($categories_array);

            $catOrdered = $this->makeRecursive($categories_array);
            if (count($catOrdered) > 0) {
                foreach ($catOrdered as $entry) {
                    $child = $categories->addChild('ITEM');
                    $child->addChild('TITLE', htmlspecialchars($entry['TITLE']));
                    $child->addChild('URL', htmlspecialchars($entry['URL']));
                    // var_dump($entry['children']);
                    if (isset($entry['children']) && ! empty($entry['children'])) {
                        $child->addChild('ITEM', $this->makeXml($entry['children'], $child));
                    }
                    $file_handle = fopen($temp, 'a+');

                    fwrite($file_handle, $child->asXml());
                    fclose($file_handle);
                }
            }

            $this->_queue->increasePage();
            return true;

        } catch (\Exception $e) {
            echo $e->getMessage();
            $this->_queue->setErrorStatus($e->getMessage());
            die();
            return null;
        }
    }

    public function createCategoryXml($file, $temp)
    {
        $category = new \SimpleXMLElement('<CATEGORIES/>');
        $category->addChild('ITEM');

        file_put_contents($file, str_replace('<ITEM/>', file_get_contents($temp), $category->asXml()));
        file_put_contents($temp, '');
        return is_file($file) ? 10 : 0;

    }

    /**
     * @param $d
     * @param int $r
     * @param string $pk
     * @param string $k
     * @param string $c
     * @return array|mixed
     */
    private function makeRecursive($d, $r = 0, $pk = 'parent', $k = 'id', $c = 'children')
    {
        $m = [];
        foreach ($d as $e) {
            isset($m[$e[$pk]]) ?: $m[$e[$pk]] = [];
            isset($m[$e[$k]]) ?: $m[$e[$k]]   = [];
            $m[$e[$pk]][]                     = array_merge($e, [$c => &$m[$e[$k]]]);
        }

        return $m[$r];
    }

    /**
     * @param $data
     * @param $node
     * @return mixed
     */
    private function makeXml($data, $node)
    {
        if (count($data) > 0) {
            foreach ($data as $entry) {
                print_r($entry);
                $child = $node->addChild('ITEM');
                $child->addChild('TITLE', htmlspecialchars($entry['TITLE']));
                $child->addChild('URL', htmlspecialchars($entry['URL']));
                // var_dump($entry['children']);
                if (isset($entry['children']) && ! empty($entry['children'])) {
                    $child->addChild('ITEM', $this->makeXml($entry['children'], $child));
                }
            }
        }
        return $node;
    }
}
