<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014  Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/
namespace Espo\Core\Utils;

use Espo\Core\Exceptions\Error;

class Metadata
{

    protected $meta = null;

    protected $scopes = array();

    protected $ormMeta = null;

    private $config;

    private $unifier;

    private $fileManager;

    private $converter;

    /**
     * @var string - uses for loading default values
     */
    private $name = 'metadata';

    private $cacheFile = 'data/cache/application/metadata.php';

    private $paths = array(
        'corePath' => 'application/Espo/Resources/metadata',
        'modulePath' => 'application/Espo/Modules/{*}/Resources/metadata',
        'customPath' => 'custom/Espo/Custom/Resources/metadata',
    );

    private $ormCacheFile = 'data/cache/application/ormMetadata.php';

    private $moduleList = null;

    public function __construct(Config $config, File\Manager $fileManager)
    {
        $this->config = $config;
        $this->fileManager = $fileManager;
        $this->unifier = new File\Unifier($this->fileManager);
        $this->converter = new Database\Converter($this, $this->fileManager);
        $this->init(!$this->isCached());
    }

    public function init($reload = false)
    {
        /**
         * @var Log $log
         */
        $data = $this->getMetadataOnly(false, $reload);
        if ($data === false) {
            $log = $GLOBALS['log'];
            $log->emergency('Metadata:init() - metadata has not been created');
        }
        $this->meta = $data;
        if ($reload) {
            //save medatada to a cache file
            $isSaved = $this->getFileManager()->putContentsPHP($this->cacheFile, $data);
            if ($isSaved === false) {
                $log = $GLOBALS['log'];
                $log->emergency('Metadata:init() - metadata has not been saved to a cache file');
            }
        }
    }

    /**
     * Get Metadata only without saving it to the a file and database sync
     *
     * @param      $isJSON
     * @param bool $reload
     *
     * @return json | array
     */
    public function getMetadataOnly($isJSON = true, $reload = false)
    {
        /**
         * @var Log $log
         */
        $data = false;
        if (!file_exists($this->cacheFile) || $reload) {
            $data = $this->getUnifier()->unify($this->name, $this->paths, true);
            if ($data === false) {
                $log = $GLOBALS['log'];
                $log->emergency('Metadata:getMetadata() - metadata unite file cannot be created');
            }
            $data = $this->setLanguageFromConfig($data);
        } else if (file_exists($this->cacheFile)) {
            $data = $this->getFileManager()->getContents($this->cacheFile);
        }
        if ($isJSON) {
            $data = Json::encode($data);
        }
        return $data;
    }

    protected function getUnifier()
    {
        return $this->unifier;
    }

    /**
     * Set language list and default for Settings, Preferences metadata
     *
     * @param array $data Meta
     *
     * @return array $data
     */
    protected function setLanguageFromConfig($data)
    {
        $entityList = array(
            'Settings',
            'Preferences',
        );
        $languageList = $this->getConfig()->get('languageList');
        $language = $this->getConfig()->get('language');
        foreach ($entityList as $entityName) {
            if (isset($data['entityDefs'][$entityName]['fields']['language'])) {
                $data['entityDefs'][$entityName]['fields']['language']['options'] = $languageList;
                $data['entityDefs'][$entityName]['fields']['language']['default'] = $language;
            }
        }
        return $data;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    public function isCached()
    {
        if (!$this->getConfig()->get('useCache')) {
            return false;
        }
        if (file_exists($this->cacheFile)) {
            return true;
        }
        return false;
    }

    /**
     * Get All Metadata context
     *
     * @param      $isJSON
     * @param bool $reload
     *
     * @return json | array
     */
    public function getAll($isJSON = false, $reload = false)
    {
        if ($reload) {
            $this->init();
        }
        if ($isJSON) {
            return Json::encode($this->meta);
        }
        return $this->meta;
    }

    /**
     * Set Metadata data
     * Ex. $type= menu, $scope= Account then will be created a file metadataFolder/menu/Account.json
     *
     * @param        JSON   string $data
     * @param string $type  - ex. menu
     * @param string $scope - Account
     *
     * @throws Error
     * @return bool
     */
    public function set($data, $type, $scope)
    {
        $path = $this->paths['customPath'];
        $result = $this->getFileManager()->mergeContents(array($path, $type, $scope . '.json'), $data, true);
        if ($result === false) {
            throw new Error("Error saving metadata. See log file for details.");
        }
        $this->init(true);
        return $result;
    }

    /**
     * Unset some fields and other stuff in metadat
     *
     * @param  array | string $unsets Ex. 'fields.name'
     * @param  string         $type   Ex. 'entityDefs'
     * @param  string         $scope
     *
     * @return bool
     */
    public function delete($unsets, $type, $scope)
    {
        /**
         * @var Log $log
         */
        $path = $this->paths['customPath'];
        $result = $this->getFileManager()->unsetContents(array($path, $type, $scope . '.json'), $unsets, true);
        if ($result == false) {
            $log = $GLOBALS['log'];
            $log->warning('Delete metadata items available only for custom code.');
        }
        $this->init(true);
        return $result;
    }

    public function getOrmMetadata($reload = false)
    {
        if (!empty($this->ormMeta) && !$reload) {
            return $this->ormMeta;
        }
        if (!file_exists($this->ormCacheFile) || !$this->getConfig()->get('useCache') || $reload) {
            $this->getConverter()->process();
        }
        $this->ormMeta = $this->getFileManager()->getContents($this->ormCacheFile);
        return $this->ormMeta;
    }

    protected function getConverter()
    {
        return $this->converter;
    }

    public function setOrmMetadata(array $ormMeta)
    {
        $result = $this->getFileManager()->putContentsPHP($this->ormCacheFile, $ormMeta);
        if ($result == false) {
            throw new Error('Metadata::setOrmMetadata() - Cannot save ormMetadata to a file');
        }
        $this->ormMeta = $ormMeta;
        return $result;
    }

    /**
     * Get Entity path, ex. Espo.Entities.Account or Modules\Crm\Entities\MyModule
     *
     * @param string      $entityName
     * @param bool|string $delim - delimiter
     *
     * @return string
     */
    public function getEntityPath($entityName, $delim = '\\')
    {
        $path = $this->getScopePath($entityName, $delim);
        return implode($delim, array($path, 'Entities', Util::normilizeClassName(ucfirst($entityName))));
    }

    /**
     * Get Scope path, ex. "Modules/Crm" for Account
     *
     * @param string $scopeName
     * @param string $delim - delimiter
     *
     * @return string
     */
    public function getScopePath($scopeName, $delim = '/')
    {
        $moduleName = $this->getScopeModuleName($scopeName);
        $path = ($moduleName !== false) ? 'Espo/Modules/' . $moduleName : 'Espo';
        if ($delim != '/') {
            $path = str_replace('/', $delim, $path);
        }
        return $path;
    }

    /**
     * Get module name if it's a custom module or empty string for core entity
     *
     * @param string $scopeName
     *
     * @return string
     */
    public function getScopeModuleName($scopeName)
    {
        return $this->get('scopes.' . $scopeName . '.module', false);
    }

    /**
     * Get Metadata
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return array
     */
    public function get($key = null, $default = null)
    {
        return Util::getValueByKey($this->getData(), $key, $default);
    }

    /**
     * Get unified metadata
     *
     * @return array
     */
    protected function getData()
    {
        if (!isset($this->meta)) {
            $this->init();
        }
        return $this->meta;
    }

    public function getRepositoryPath($entityName, $delim = '\\')
    {
        $path = $this->getScopePath($entityName, $delim);
        return implode($delim, array($path, 'Repositories', Util::normilizeClassName(ucfirst($entityName))));
    }

    /**
     * Get Module List
     *
     * @return array
     */
    public function getModuleList()
    {
        if (is_null($this->moduleList)) {
            $this->moduleList = array();
            $scopes = $this->getScopes();
            // TODO order
            foreach ($scopes as $moduleName) {
                if (!empty($moduleName)) {
                    if (!in_array($moduleName, $this->moduleList)) {
                        $this->moduleList[] = $moduleName;
                    }
                }
            }
        }
        return $this->moduleList;
    }

    /**
     * Get Scopes
     *
     * @return array
     */
    public function getScopes()
    {
        if (!empty($this->scopes)) {
            return $this->scopes;
        }
        $metadata = $this->getMetadataOnly(false);
        $scopes = array();
        foreach ($metadata['scopes'] as $name => $details) {
            $scopes[$name] = isset($details['module']) ? $details['module'] : false;
        }
        return $this->scopes = $scopes;
    }

    /**
     * Check if scope exists
     *
     * @param string $scopeName
     *
     * @return bool
     */
    public function isScopeExists($scopeName)
    {
        $scopeModuleMap = $this->getScopes();
        $lowerEntityName = strtolower($scopeName);
        foreach ($scopeModuleMap as $rowEntityName => $rowModuleName) {
            if ($lowerEntityName == strtolower($rowEntityName)) {
                return true;
            }
        }
        return false;
    }
}
