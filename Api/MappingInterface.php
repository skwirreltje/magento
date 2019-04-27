<?php
namespace Skwirrel\Pim\Api;

interface MappingInterface
{

    public function getProcesses();

    public function getProcess($entityName);

    public function load();

    public function getAttributes();

    public function getMappingXml();

    public function getWebsites();

    public function getLanguages();

    public function getDefaultLanguage();


}