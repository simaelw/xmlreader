<?php

use ConfigCache as GlobalConfigCache;

interface Cache
{
    public function isCached(): bool;
    public function isFresh($file): bool;
    public function read();
    public function write($config);
}

class ConfigCache implements Cache
{
    private $cache_file;

    public function __construct($file)
    {
        // Imposta il percorso del file
        $this->cache_file = sha1($file) . ".cache";
    }

    public function isCached(): bool
    {
        // Verifica se il file esiste
        return file_exists($this->cache_file);
    }

    public function isFresh($file): bool
    {
        // Verifica se il file è stato modificato
        return filemtime($file) < filemtime($this->cache_file);
    }

    public function read()
    {
        // Legge il file
        return unserialize(file_get_contents($this->cache_file));
    }

    public function write($config)
    {
        // Salva il file di cache
        file_put_contents($this->cache_file, serialize($config));
    }
}


class XMLConfig
{
    private $config = [];
    private Cache $cache;
    private $xmlFiles = [];

    public function __construct($configFile)
    {
        // Verifica se il file di configurazione esiste
        if (!file_exists($configFile)) {
            throw new Exception("Il file di configurazione XML non esiste: " . $configFile);
        }

        // Inietta la dipendenza della classe per la gestione del caching
        $this->cache = new ConfigCache($configFile);

        // Verifica se è presente un file di cache
        if ($this->cache->isCached($configFile) && $this->cache->isCached($configFile)) {
            $this->config = $this->cache->read();
        } else {
            $this->xmlFiles[] = $configFile;
            $this->parseXML();
            $this->cache->write($this->config);
        }
    }

    private function parseXML()
    {
        // Itera su tutti i file XML
        while (!empty($this->xmlFiles)) {
            $xmlFile = array_shift($this->xmlFiles);

            // Carica il contenuto del file XML
            $xml = simplexml_load_file($xmlFile);

            // Verifica se ci sono direttive di importazione
            $imports = $xml->xpath("//glz:Import");
            foreach ($imports as $import) {
                $importFile = dirname($xmlFile) . "/" . (string)$import["src"];
                $this->xmlFiles[] = $importFile;
            }

            // Itera su tutti i gruppi del file XML
            $groups = $xml->xpath("./glz:Group");
            $this->parseGroups($groups);

            // Itera su tutti i parametri del file XML
            $params = $xml->xpath("./glz:Param");
            $this->config = array_merge($this->config, $this->parseParams($params));
        }
        arsort($this->config);
    }


    private function parseGroups($groups)
    {
        // $groups = $xml->xpath("//glz:Group");
        foreach ($groups as $group) {
            $groupName = (string)$group["name"];
            if (!isset($this->config[$groupName])) {
                $this->config[$groupName] = [];
            }
            $groupArray = &$this->config[$groupName];

            // Itera su tutti i gruppi all'interno del gruppo
            $groups = $group->xpath("./glz:Group");
            $this->config[$groupName] = array_merge($this->config[$groupName], $this->parseSubGroups($groups));

            // Itera su tutti i parametri all'interno del gruppo
            $params = $group->xpath("./glz:Param");
            $this->config[$groupName] = array_merge($this->config[$groupName], $this->parseParams($params));
        }
    }

    private function parseSubGroups($groups)
    {
        // $groups = $xml->xpath("//glz:Group");
        $data = [];
        foreach ($groups as $group) {
            $groupName = (string)$group["name"];
            if (!isset($this->config[$groupName])) {
                $this->config[$groupName] = [];
            }
            $groupArray = &$this->config[$groupName];

            // Itera su tutti i parametri all'interno del gruppo
            $params = $group->xpath(".//glz:Param");

            $data[$groupName] = $this->parseParams($params);
        }
        return $data;
    }

    private function parseParams($params)
    {
        // $params = $xml->xpath("//glz:Param");
        $data = [];
        foreach ($params as $param) {
            $name = (string)$param["name"];
            $value = (string)$param["value"];

            // Verifica se il valore è racchiuso tra i tag CDATA
            $inner_value = (string)$param;

            // Verifica se il nome del parametro è un array
            if (substr($name, -2) === "[]") {
                $name = substr($name, 0, -2);
                if (!isset($data[$name])) {
                    $data[$name] = [];
                }
                $data[$name][] = $value;
            } else if (substr($inner_value, 0, 9) == "<![CDATA[" && substr($inner_value, -3) == "]]>") {
                $data[$name] = substr($inner_value, 9, -3);
            } else {
                $data[$name] = $value;
            }
        }
        return $data;
    }

    public function get($path)
    {
        $keys = explode('/', $path);
        $result = $this->config;

        foreach ($keys as $key) {
            if (isset($result[$key])) {
                $result = $result[$key];
            } else {
                return null;
            }
        }

        return $result;
    }
}

$config = new XMLConfig('myConfig.xml');

var_dump($config->get('archive'));
var_dump($config->get('imageCache'));
var_dump($config->get('mode'));
var_dump($config->get('jpg_compression'));
var_dump($config->get('thumbnail/width'));
var_dump($config->get('thumbnail/height'));
var_dump($config->get('thumbnail/crop'));
var_dump($config->get('thumbnail/filters'));
var_dump($config->get('medium/width'));
var_dump($config->get('medium/height'));
var_dump($config->get('medium/crop'));
var_dump($config->get('full/width'));
var_dump($config->get('full/height'));
var_dump($config->get('full/crop'));
var_dump($config->get('arrayvalue'));
var_dump($config->get('group/innergroup/value1'));
var_dump($config->get('group/innergroup/value2'));
var_dump($config->get('longtext'));
var_dump($config->get('value1'));
var_dump($config->get('value2'));
var_dump($config->get('value3'));
