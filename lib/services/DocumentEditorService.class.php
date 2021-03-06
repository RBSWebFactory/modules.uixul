<?php

class uixul_DocumentEditorService extends BaseService
{
	const TAG_ATTRIBUTE_NAME = "tag";
	const LABEL_ATTRIBUTE_NAME = "label";
	const ATTR_ATTRIBUTE_NAME = "attributes";
	
	/**
	 * @var uixul_DocumentEditorService
	 */
	private static $instance;
	
	/**
	 * @var array<moduleName<modelName>>
	 */
	private $models = array();
	
	/**
	 * @var array<modelName<panelName>>
	 */
	private $documentpanels = array();
	
	/**
	 * @return uixul_DocumentEditorService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	private static $stdPanels = array(
		'resume' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.resume', 'icon' => 'resume-section'),
		'properties' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.properties', 'icon' => 'edit-properties'),
		'localization' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.localization', 'icon' => 'translate'),
		'publication' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.status', 'icon' => 'status'),
		'redirect' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.redirect', 'icon' => 'urlrewriting'),
		'permission' =>  array('labeli18n' => 'm.uixul.bo.doceditor.tab.permission', 'icon' => 'rights-management'),
		'history' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.history', 'icon' => 'history'),
		'create' => array('labeli18n' => 'm.uixul.bo.doceditor.tab.create', 'icon' => 'add'));
	
	/**
	 * Retourne le binding des editeurs du documents
	 * @param string $moduleName
	 * @return string
	 */
	public function getCSSBindingForModule($moduleName)
	{
		$editorsConfig = $this->getEditorsConfigForModule($moduleName);
		$str = array();
		if (count($editorsConfig) > 0)
		{
			foreach ($editorsConfig as $editorConfig)
			{
				$str[] = $this->getCSSBindingForModel($moduleName, $editorConfig);
			}
		}
		return implode("\n", $str);
	}	
	
	
	/**
	 * @param string $moduleName
	 * @return string
	 */
	public function getDocumentEditorsForModule($moduleName)
	{
		$editorsConfig = $this->getEditorsConfigForModule($moduleName);
		$str = array();
		if (count($editorsConfig) > 0)
		{
			foreach ($editorsConfig as $editorConfig)
			{
				$str[] = $this->getPerspectiveDocument($moduleName, $editorConfig);
			}
		}
		return implode("\n", $str);
	}
	
	/**
	 * @param string $moduleName
	 * @param array $editorConfig
	 * @return String
	 */
	private function getPerspectiveDocument($moduleName, $editorConfig)
	{
		//f_util_ProcessUtils::printBackTrace();
		$model = $this->getModelByName($editorConfig['modelName']);	
		$documentName = $model->getDocumentName();
		
		if ($moduleName === 'preferences' && $documentName === 'preferences')
		{
			$documentName = $model->getModuleName();
		}
		
		$id = 'edt_' . $editorConfig['moduleName'] . '_' . $editorConfig['editorFolderName'];
		$modelName = $model->getName();
		$domDocument = $this->getNewDOMDocument();
		$editordom = $domDocument->appendChild($domDocument->createElement('cdocumenteditor'));
		$editordom->setAttribute("anonid", $id);
		$editordom->setAttribute("documentname", $documentName);
		$editordom->setAttribute("modelname", $modelName);
		$editordom->setAttribute("module", $moduleName);
		$editordom->setAttribute("collapsed", "true");
		$editordom->setAttribute("flex", "1");
		return $domDocument->saveXML($domDocument->documentElement);
	}
	

	
	/**
	 * @param string $moduleName
	 * @param array $editorConfig array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 * @return string
	 */
	private function getCSSBindingForModel($moduleName, $editorConfig)
	{
		$editorFolderName = $editorConfig['editorFolderName'];
		$result = array();
		$bindingModuleName = isset($editorConfig['bindingModuleName']) ? $editorConfig['bindingModuleName'] : $editorConfig['moduleName'];
		$link = LinkHelper::getUIChromeActionLink('uixul', 'GetBinding')		
				->setQueryParameter('binding', 'modules.' . $bindingModuleName . '.editors.' . $editorFolderName)
				->setQueryParameter('uilang', RequestContext::getInstance()->getUILang());

		$id = 'edt_' . $editorConfig['moduleName'] . '_' . $editorFolderName;		
		$result[] = '#' . $id . ' {-moz-binding: url(' . $link->setFragment($editorFolderName)->getUrl() . ');}';
		foreach (array_keys($editorConfig['panels']) as $panelName) 
		{
			$fragment = $editorFolderName . '_' . $panelName;
			$tagname = 'c' . $panelName . 'panel';
			$result[] = '#' . $id . ' ' . $tagname . ' {-moz-binding: url(' . $link->setFragment($fragment)->getUrl() . ');}';
		}
		return implode("\n", $result);
	}
	
	/**
	 * @param string $moduleName
	 * @param string $documentName
	 * @return string
	 */
	public function getEditorsBinding($moduleName, $documentName = null)
	{
		if (headers_sent() === false)
		{
			header('Content-type: text/xml');
		}		
		$bindingsDoc = $this->getNewBindingsDOMDocument();
		if ($documentName === 'preferences')
		{
			$editorConfig = $this->getEditorConfig('preferences', $moduleName);
		}
		else
		{
			$editorConfig = $this->getEditorConfig($moduleName, $documentName);
		}

		if ($editorConfig === null)
		{
			throw new Exception("Invalid editor for document $documentName in module $moduleName");
		}
			
		$this->getEditorBinding($bindingsDoc, $editorConfig);
		
		$bindingsDoc->formatOutput = true;
		$tr = new f_util_TagReplacer();
		$tr->setReplacement('HttpHost', Framework::getUIBaseUrl());
		$tr->setReplacement('IconsBase', MediaHelper::getIconBaseUrl());
		return $tr->run($bindingsDoc->saveXML(), true);
	}
	
	/**
	 * @param DOMDocument $bindingsDoc
	 * @param array $editorConfig array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 * @return string
	 */
	private function getEditorBinding($bindingsDoc, $editorConfig)
	{
		$this->addEditorBinding($bindingsDoc, $editorConfig);
		$panels = array_keys($editorConfig['panels']);
		foreach ($panels as $panelName)
		{
			$this->addEditorPanelBinding($bindingsDoc, $panelName, $editorConfig);
		}
		
		$xpath = $this->getBindingXPath($bindingsDoc);
		$bindingNodes = $xpath->query('//xbl:binding[@extends]');
		foreach ($bindingNodes as $bindingNode)
		{
			$extend = uixul_lib_BindingObject::getUrl($bindingNode->getAttribute("extends"));
			$bindingNode->setAttribute("extends", $extend);
		}
		
		$rc = RequestContext::getInstance();
		$stylesheetNodes = $xpath->query('//xbl:stylesheet[@src]');
		foreach ($stylesheetNodes as $stylesheetNode)
		{
			$src = LinkHelper::getUIChromeActionLink("uixul", "GetUICSS")
    					->setQueryParameter('uilang', $rc->getUILang())
						->setQueryParameter('stylename', $stylesheetNode->getAttribute('src'))
						->setArgSeparator(f_web_HttpLink::STANDARD_SEPARATOR);
			$stylesheetNode->setAttribute("src", $src->getUrl());
		}
	}
	
	/**
	 * @param string $moduleName
	 */
	public function compileDocumentEditors($moduleName)
	{
		$editorsConfig = $this->getEditorsConfigForModule($moduleName);
		if (count($editorsConfig) > 0)
		{
			foreach ($editorsConfig as $editorConfig)
			{
				$this->compileEditorBinding($moduleName, $editorConfig);
			}
		}
	}
	
	/**
	 * @param string $moduleName
	 * @param array $editorConfig <editorFolderName => array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 */
	private function compileEditorBinding($moduleName, $editorConfig)
	{
		$editorFolderName = $editorConfig['editorFolderName'];		
		$basePath = f_util_FileUtils::buildChangeBuildPath('modules', $moduleName, 'lib', 'bindings', 'editor', $editorFolderName);
		if (is_dir($basePath))
		{
			f_util_FileUtils::clearDir($basePath);
		}
		else
		{
			f_util_FileUtils::mkdir($basePath);
		}
		
		$path = f_util_FileUtils::buildPath($basePath, 'editor.xml');
		$doc = $this->getNewBindingsDOMDocument();
		$this->addEditorBinding($doc, $editorConfig);
		$doc->save($path);
		$panels = array_keys($editorConfig['panels']);	
		foreach ($panels as $panelName)
		{
			$path = f_util_FileUtils::buildPath($basePath, $panelName . '.xml');
			$doc = $this->getNewBindingsDOMDocument();
			$this->addEditorPanelBinding($doc, $panelName, $editorConfig);
			$doc->save($path);
		}
	}
	
	public function buildDefaultDocumentEditors($moduleName, $documentName = null, $panels = null)
	{
		$editorsConfig = $this->getEditorsConfigForModule($moduleName);
		if (count($editorsConfig) > 0)
		{
			foreach ($editorsConfig as $editorFolderName => $editorConfig)
			{
				if ($documentName === null || $editorFolderName == $documentName)
				{
					$this->buildDefaultEditorBinding($moduleName, $editorConfig, $panels);
				}
			}
		}
	}
	
	/**
	 * @param string $moduleName
	 * @param array $editorConfig <editorFolderName => array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 * @param array $panels
	 */
	private function buildDefaultEditorBinding($moduleName, $editorConfig, $panels = null)
	{
		$editorFolderName = $editorConfig['editorFolderName'];	
		$basePath = f_util_FileUtils::buildWebeditPath('modules', $moduleName, 'forms', 'editor', $editorFolderName);
		f_util_FileUtils::mkdir($basePath);
		
		if (f_util_ArrayUtils::isEmpty($panels))
		{
			$panels = array_keys($editorConfig['panels']);
		}
		
		$path = f_util_FileUtils::buildPath($basePath, 'panels.xml');
		
		if (!file_exists($path) && in_array("panels", $panels))
		{
			$panelsDoc = f_util_DOMUtils::fromString('<panels />');
			foreach ($panels as $panelName)
			{
				$panel = $panelsDoc->createElement('panel');
				$panel->setAttribute('name', $panelName);
				$panelsDoc->documentElement->appendChild($panel);
			}
			f_util_DOMUtils::save($panelsDoc, $path);
		}
		
		foreach ($panels as $panelName)
		{
			$path = f_util_FileUtils::buildPath($basePath, $panelName . '.xml');
			if (!file_exists($path))
			{
				$defPath = $this->getPanelDefinitionPath($panelName, $moduleName, $editorFolderName);
				if ($defPath === null)
				{
					throw new Exception(__METHOD__ . ": could not build panel $panelName for $moduleName/$editorFolderName");
				}
				
				$content = file_get_contents($defPath);
				$tr = uixul_lib_DocumentEditorPanelTagReplacer::getInstance($panelName, $moduleName, $editorConfig['modelName']);
				$panelDefDoc = f_util_DOMUtils::fromString($tr->run($content));
				f_util_DOMUtils::save($panelDefDoc, $path);
				echo "$path generated\n";
			}
			else
			{
				echo "$path already exists\n";
			}
		}
	}
	
	/**
	 * @param String $moduleName
	 * @return array<editorFolderName => array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 */
	private function getEditorsConfigForModule($moduleName)
	{
		if (!isset($this->models[$moduleName]))
		{
			$compiledFilePath = f_util_FileUtils::buildChangeBuildPath('modules', $moduleName, 'forms', 'editors.php');
			if (file_exists($compiledFilePath))
			{
				$this->models[$moduleName] = unserialize(file_get_contents($compiledFilePath));
			}
			else
			{
				$this->models[$moduleName] = array();
			}
		}
		
		return $this->models[$moduleName];
	}
	
	/**
	 * @param String $moduleName
	 * @param String $documentName
	 * @return array<moduleName, editorFolderName, modelName, panels<name => true>>
	 */
	private function getEditorConfig($moduleName, $editorFolderName)
	{
		$configs = $this->getEditorsConfigForModule($moduleName);
		if (isset($configs[$editorFolderName]))
		{
			return $configs[$editorFolderName];
		}
		return null;
	}
	
	/**
	 * @param string $documentModelName
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	private function getModelByName($documentModelName)
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($documentModelName);
	}
	
	/**
	 * @return DOMDocument
	 */
	private function getNewDOMDocument()
	{
		return new DOMDocument('1.0', 'UTF-8');
	}
	
	/**
	 * @param string $moduleName
	 * @param string $documentName
	 * @param boolean $localized
	 * @return array
	 */
	private function getPanels($moduleName, $documentName, $localized, $hasURL = true)
	{
		$key = $moduleName . $documentName;
		if (! isset($this->documentpanels[$key]))
		{
			$path = FileResolver::getInstance()->setPackageName('modules_' . $moduleName)->setDirectory(f_util_FileUtils::buildPath('forms', 'editor', $documentName))->getPath('panels.xml');
			if ($path === null)
			{
				// TODO: code
				$panels = $this->getDefaultPanels($documentName, $localized, $hasURL);
			}
			else
			{
				$panels = array();
				$panelsDoc = new DOMDocument();
				$panelsDoc->load($path);
				$plist = $panelsDoc->getElementsByTagName('panel');
				foreach ($plist as $panel)
				{
					$panels[] = $panel->getAttribute('name');
				}
			}
			$this->documentpanels[$key] = $panels;
		}
		
		return $this->documentpanels[$key];
	}
		
	/**
	 * @return DOMDocument
	 */
	private function getNewBindingsDOMDocument()
	{
		$domDocument = $this->getNewDOMDocument();
		$domDocument->loadXML('<?xml version="1.0" encoding="UTF-8"?><bindings xmlns="http://www.mozilla.org/xbl" xmlns:xbl="http://www.mozilla.org/xbl" xmlns:html="http://www.w3.org/1999/xhtml" xmlns:xul="http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul" />');
		return $domDocument;
	}
	
	/**
	 * @param DOMDocument $document
	 * @return DOMXPath
	 */
	private function getBindingXPath($document)
	{
		$xpath = new DOMXPath($document);
		$xpath->registerNamespace('xbl', 'http://www.mozilla.org/xbl');
		return $xpath;
	}
	
	/**
	 * @param DOMDocument $bindingsDoc
	 * @param array $editorConfig array<editorFolderName => array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 */
	private function addEditorBinding($bindingsDoc, $editorConfig)
	{
		$moduleName = $editorConfig['moduleName'];
		$editorFolderName = $editorConfig['editorFolderName'];
		$path = FileResolver::getInstance()->setPackageName('modules_' . $moduleName)
			->setDirectory(f_util_FileUtils::buildPath('lib', 'bindings', 'editor', $editorFolderName))
			->getPath('editor.xml');
		if ($path === null)
		{
			$bindingDoc = $this->getPanelFromDefinition('panels', $editorConfig);
		}
		else
		{
			$bindingDoc = new DOMDocument();
			$bindingDoc->load($path);
		}
		$node = $bindingDoc->getElementsByTagName('binding')->item(0);
		$bindingsDoc->documentElement->appendChild($bindingsDoc->importNode($node, true));
	}
	
	/**
	 * @param DOMDocument $bindingsDoc
	 * @param string $panelName
	 * @param array $editorConfig array<editorFolderName => array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 */
	private function addEditorPanelBinding($bindingsDoc, $panelName, $editorConfig)
	{
		$moduleName = $editorConfig['moduleName'];
		$editorFolderName = $editorConfig['editorFolderName'];
		$path = FileResolver::getInstance()
			->setPackageName('modules_' . $moduleName)
			->setDirectory(f_util_FileUtils::buildPath('lib', 'bindings', 'editor', $editorFolderName))
			->getPath($panelName . '.xml');
		if ($path === null)
		{
			if (!isset(self::$stdPanels[$panelName])) {return;}
			$bindingDoc = $this->getPanelFromDefinition($panelName, $editorConfig);
		}
		else
		{ 
			$bindingDoc = new DOMDocument();
			$bindingDoc->load($path);
		}
		foreach ($bindingDoc->getElementsByTagName('binding') as $node) 
		{
			$bindingsDoc->documentElement->appendChild($bindingsDoc->importNode($node, true));
		}
	}
	
	/**
	 * @param string $panelName
	 * @param array $editorConfig array<editorFolderName => array<moduleName, editorFolderName, modelName, panels<name => true>>>
	 *
	 * @return DOMDocument
	 */
	private function getPanelFromDefinition($panelName, $editorConfig)
	{
		$moduleName = $editorConfig['moduleName'];
		$editorFolderName = $editorConfig['editorFolderName'];
		$path = $this->getPanelDefinitionPath($panelName, $moduleName, $editorFolderName);
		$tr = uixul_lib_DocumentEditorPanelTagReplacer::getInstance($panelName, $moduleName, $editorConfig['modelName']);
		$content = file_get_contents($path);
		$panelDefDoc = new DOMDocument();
		$panelDefDoc->loadXML($tr->run($content));
		$docElement = $panelDefDoc->documentElement;
		
		// Handle "use" attribute.
		if ($docElement !== null && $docElement->hasAttribute('use'))
		{
			$use = $docElement->getAttribute('use');
			if (strpos($use, '.'))
			{
				$useModelName = $docElement->hasAttribute('model') ? $docElement->getAttribute('model') : null;				
				$usepanelName = $panelName;
				$useeditorFolderName = $editorFolderName;
				$usemoduleName = $moduleName;
				
				$useInfo = explode('.', $use);
				if (count($useInfo) == 2)
				{
					$usepanelName = $useInfo[1];
					$useeditorFolderName = $useInfo[0];
				}
				else if (count($useInfo) == 3)
				{
					$usepanelName = $useInfo[2];
					$useeditorFolderName = $useInfo[1];
					$usemoduleName = $useInfo[0];					
				}
				$path = $this->getPanelDefinitionPath($usepanelName, $usemoduleName, $useeditorFolderName);
				$content = file_get_contents($path);
				$panelDefDoc->loadXML($tr->run($content));
				$docElement = $panelDefDoc->documentElement;
				
				if ($useModelName)
				{
					$docElement->setAttribute('model', $useModelName);
				}
				else
				{
					$docElement->removeAttribute('model');
				}
			}
			else
			{
				$path = $this->getPanelDefinitionPath($use, $moduleName, $editorFolderName);
				$content = file_get_contents($path);
				$panelDefDoc->loadXML($tr->run($content));
				$docElement = $panelDefDoc->documentElement;
			}
		}
		
		if ($docElement && $docElement->hasAttribute('model'))
		{
			self::$XSLCurrentModel = $this->getModelByName($docElement->getAttribute('model'));
		}
		else
		{
			self::$XSLCurrentModel = $this->getModelByName($editorConfig['modelName']);
		}
		
		if ($panelName === 'panels' && isset($editorConfig['panels']))
		{
			$this->addStdPanels($panelDefDoc, $editorConfig['panels']);
		}
		
		$xslPath = FileResolver::getInstance()->setPackageName('modules_uixul')
			->setDirectory(f_util_FileUtils::buildPath('forms', 'editor', 'xul'))
			->getPath($panelName . '.xsl');
		$xsl = new DOMDocument('1.0', 'UTF-8');
		$xsl->load($xslPath);
		$xslt = new XSLTProcessor();
		$xslt->registerPHPFunctions();
		$xslt->importStylesheet($xsl);
		$xslt->setParameter('', 'moduleName', $moduleName);
		$xslt->setParameter('', 'documentName', $editorFolderName);
		$xslt->setParameter('', 'panelName', $panelName);
		$panelDoc = $xslt->transformToDoc($panelDefDoc);
		
		return $panelDoc;
	}
	
	/**
	 * 
	 * @param DOMDocument $panelDefDoc
	 * @param array $panels
	 */
	private function addStdPanels($panelDefDoc, $panels)
	{
		if ($panelDefDoc->getElementsByTagName('panel')->length == 0)
		{
			$docElem = $panelDefDoc->documentElement;
			foreach ($panels as $name => $data) 
			{
				$elem = $docElem->appendChild($panelDefDoc->createElement('panel'));
				$elem->setAttribute('name', $name);
			}
		}
	}
	
	/**
	 * @param string $panelName
	 * @param string $moduleName
	 * @param string $documentName
	 * @return String
	 */
	private function getPanelDefinitionPath($panelName, $moduleName, $documentName)
	{
		$path = FileResolver::getInstance()->setPackageName('modules_' . $moduleName)->setDirectory(f_util_FileUtils::buildPath('forms', 'editor', $documentName))->getPath($panelName . '.xml');
		if ($path === null)
		{
			$path = FileResolver::getInstance()->setPackageName('modules_uixul')->setDirectory(f_util_FileUtils::buildPath('forms', 'editor'))->getPath($panelName . '_' . $documentName . '.xml');
			if ($path === null)
			{
				$path = FileResolver::getInstance()->setPackageName('modules_uixul')->setDirectory(f_util_FileUtils::buildPath('forms', 'editor'))->getPath($panelName . '.xml');
			}
		}
		return $path;
	}
	
	/**
	 * @var f_persistentdocument_PersistentDocumentModel
	 */
	private static $XSLCurrentModel;
	/**
	 * @var string
	 */
	private static $XSLCurrentModule;
	
	/**
	 * @var string
	 */
	private static $XSLCurrentPanel;
	
	/**
	 * @var array
	 */
	private static $XSLCurrentFields;
	
	public static function XSLExpandAllowAttribute($elementArray)
	{
		$element = $elementArray[0];
		return DocumentHelper::expandAllowAttribute($element->value);
	}


	
	public static function XSLSetDefaultPanelInfo($elementArray)
	{
		$element = $elementArray[0];
		$panelName = $element->getAttribute("name");
		if (!$panelName)
		{
			throw new Exception('Invalid empty panel name');
		}

		if (isset(self::$stdPanels[$panelName]))
		{
			$extraInfo = self::$stdPanels[$panelName];
		}
		else
		{
			$extraInfo = array();
		}
		
		if (!$element->hasAttribute('labeli18n'))
		{
			if (isset($extraInfo['labeli18n']))
			{
				$element->setAttribute('labeli18n', $extraInfo['labeli18n']);
			}
			else
			{
				$element->setAttribute('labeli18n', 'm.'.self::$XSLCurrentModel->getModuleName().'.bo.doceditor.tab.'.$panelName);
			}
		}
		if (!$element->hasAttribute('icon') && isset($extraInfo['icon']))
		{
			$element->setAttribute('icon', $extraInfo['icon']);
		}
		return $element;
	}

	public static function XSLGetBindingId($moduleName, $documentName, $panelName)
	{
		self::$XSLCurrentModule = $moduleName;
		self::$XSLCurrentPanel = $panelName;
		self::$XSLCurrentFields = array();
		return $documentName . '_' . $panelName;
	}
	
	public static function XSLSetDefaultFieldInfo($elementArray)
	{
		$element = $elementArray[0];
		$name = $element->getAttribute("name");
		if (! $name)
		{
			throw new Exception('Invalid empty field name');
		}
		if (isset(self::$XSLCurrentFields[$name]))
		{
			throw new Exception('Duplicate field :' . $name);
		}
		self::$XSLCurrentFields[$name] = true;
		self::updatePropertyIds($element, self::$XSLCurrentModel->getDocumentName());
		$property = self::$XSLCurrentModel->getEditableProperty($name);
		if ($property)
		{
			self::updatePropertyField($property, self::$XSLCurrentModel, $element);
		}
		else
		{
			if (!$element->hasAttribute('type'))
			{
				$element->setAttribute('type', 'text');
			}
			if (!$element->hasAttribute('shorthelp'))
			{
				$element->setAttribute('hidehelp', 'true');
			}
		}
		return $element;
	}
		
	public static function XSLFieldsName()
	{
		return JsonService::getInstance()->encode(array_keys(self::$XSLCurrentFields));
	}
	
	private static function updatePropertyIds($element, $documentName)
	{
		$propertyName = $element->getAttribute('name');
		$element->setAttribute('anonid', 'field_' . $propertyName);
		
		if (! $element->hasAttribute('id'))
		{
			$id = self::$XSLCurrentModule . '_' . $documentName;
			if (self::$XSLCurrentPanel !== 'properties')
			{
				$id .= '_' . self::$XSLCurrentPanel;
			}
			$element->setAttribute('id', $id . '_' . $propertyName);
		}
	}
	
	/**
	 * @param PropertyInfo $property
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @param DOMElement $element
	 */
	private static function updatePropertyField($property, $model, $element)
	{
		$propertyName = $property->getName();
		if (!$element->hasAttribute('hidehelp'))
		{
			if (!$element->hasAttribute('shorthelp'))
			{
				$helpKey = 'm.' . $model->getModuleName() . '.document.' . $model->getDocumentName() . '.' . $propertyName . '-help';
				$help = LocaleService::getInstance()->transBO($helpKey);
				if ($help && $help != $helpKey)
				{
					$element->setAttribute('shorthelp', $helpKey);
				}
				else
				{
					$element->setAttribute('hidehelp', 'true');
				}
			}
		}
		else
		{
			if ($element->hasAttribute('shorthelp'))
			{
				$element->removeAttribute('shorthelp');
			}
		}
		
		$listid = self::getListId($model, $propertyName);
		
		if ($listid)
		{
			if (! $element->hasAttribute('listid'))
			{
				$element->setAttribute('listid', $listid);
			}
			$type = ($property->isArray()) ? "multiplelist" : "dropdownlist";
		}
		else if ($property->isDocument())
		{
			$doctype = str_replace('/', '_', $property->getType());
			$parts = explode("_", $doctype);
			if (! $element->hasAttribute('moduleselector'))
			{
				$element->setAttribute('moduleselector', $parts[1]);
			}
			if (! $element->hasAttribute('allow'))
			{
				$element->setAttribute('allow', $doctype);
			}
			$type = ($property->isArray()) ? "documentarray" : "document";
			if ($type == "documentarray")
			{
				$constraintArray = array();
				$minOccurs = $property->getMinOccurs();
				if ($minOccurs > 0)
				{
					$constraintArray['minSizeArray'] = $minOccurs;
				}
				// case -1 (for no limit) : no constraint.
				$maxOccurs = $property->getMaxOccurs();
				if ($maxOccurs > 1)
				{
					$constraintArray['maxSizeArray'] = $maxOccurs;
				}
				self::addContraints($element, $constraintArray);
			}
		}
		else
		{
			switch ($property->getType())
			{
				case 'Boolean' :
					$type = 'boolean';
					break;
				case 'Integer' :
					$type = 'integer';
					break;
				case 'Integer' :
					$type = 'integer';
					break;
				case 'Double' :
					$type = 'double';
					break;
				case 'DateTime' :
					$type = 'datetime';
					break;
				case 'Lob' :
				case 'LongString' :
					$type = 'longtext';
					break;
				case 'XHTMLFragment' :
					$type = 'richtext';
					break;
				default :
					$type = 'text';
					break;
			}
		}
		if (! $element->hasAttribute('type'))
		{
			$element->setAttribute('type', $type);
		}
		
		if ($property->getMinOccurs() > 0)
		{
			if (! $element->hasAttribute('required'))
			{
				$element->setAttribute('required', 'true');
			}
		}
		if (! $element->hasAttribute('label') && ! $element->hasAttribute('labeli18n'))
		{
			$labeli18n = 'm.' . $model->getModuleName() . '.document.' . $model->getDocumentName() . '.' . $propertyName;
			$element->setAttribute('labeli18n', $labeli18n);
		}
		
		if ($property->getConstraints())
		{
			$constraintsParser = new validation_ContraintsParser();
			$constraintArray = $constraintsParser->getConstraintArrayFromDefinition($property->getConstraints());
			self::addContraints($element, $constraintArray);
		}
	}
	
	/**
	 * @param DOMElement $element
	 * @param array $constraintArray
	 */
	protected static function addContraints($element, $constraintArray)
	{
		foreach ($constraintArray as $name => $value)
		{
			if ($name === 'blank')
			{
				continue;
			}
			$cn = $element->appendChild($element->ownerDocument->createElement('constraint'));
			$cn->setAttribute('name', $name);
			$cn->setAttribute('parameter', $value);
		}
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @param string $propertyName
	 * @return string or null
	 */
	private static function getListId($model, $propertyName)
	{
		$property = $model->getEditableProperty($propertyName);
		return $property ? $property->getFromList() : null;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string[] $propertiesName
	 * @param integer $parentId
	 * @return array
	 */
	public function exportFieldsData($document, $propertiesName, $parentId = null)
	{
		$models = $document->getPersistentModel();
		$datas = array();
		foreach ($propertiesName as $propertyName)
		{
			$propertyInfo = $models->getEditableProperty($propertyName);
			if ($propertyInfo)
			{
				$propertyVal = $this->exportProperty($document, $propertyInfo);
			}
			else
			{
				$propertyVal = $this->exportByGetter($document, $propertyName);
			}
			
			if ($propertyVal !== null)
			{
				$datas[$propertyName] = $propertyVal;
			}
		}
		$document->getDocumentService()->addFormProperties($document, $propertiesName, $datas, $parentId);
		return $datas;
	}
	
	private function exportByGetter($document, $propertyName)
	{
		$getter = 'get' . ucfirst($propertyName);
		if (f_util_ClassUtils::methodExists($document, $getter))
		{
			$val = $document->{$getter}();
			if ($val !== null)
			{
				if ($val instanceof f_persistentdocument_PersistentDocument)
				{
					$val = $val->getId();
				}
				return strval($val);
			}
		}
		return null;
	}
	
	private function exportROByGetter($document, $propertyName)
	{
		$getter = 'getRO' . ucfirst($propertyName);
		if (f_util_ClassUtils::methodExists($document, $getter))
		{
			$val = $document->{$getter}();
			if ($val !== null)
			{
				if ($val instanceof f_persistentdocument_PersistentDocument)
				{
					$val = $val->getId();
				}
				return strval($val);
			}
			return null;
		}
		return $this->exportByGetter($document, $propertyName);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param PropertyInfo $propertyInfo
	 */
	private function exportProperty($document, $propertyInfo)
	{
		$propertyData = null;
		if ($propertyInfo->isDocument())
		{
			if ($propertyInfo->isArray())
			{
				$propertyData = array();
				$documents = $document->{'get' . ucfirst($propertyInfo->getName()) . 'Array'}();
				foreach ($documents as $subdoc)
				{
					$propertyData[] = $subdoc->getId();
				}
				$propertyData = (count($propertyData) == 0) ? null : implode(",", $propertyData);
			}
			else
			{
				$subdoc = $document->{'get' . ucfirst($propertyInfo->getName())}();
				if ($subdoc instanceof f_persistentdocument_PersistentDocument)
				{
					$propertyData = $subdoc->getId();
				}
			}
		}
		else if ($propertyInfo->getType() == f_persistentdocument_PersistentDocument::PROPERTYTYPE_BOOLEAN)
		{
			$propertyData = $document->{"get" . ucfirst($propertyInfo->getName())}() ? "true" : "false";
		}
		else if ($propertyInfo->getType() == f_persistentdocument_PersistentDocument::PROPERTYTYPE_DATETIME)
		{
			$propertyData = $document->{"getUI" . ucfirst($propertyInfo->getName())}();
		}
		else
		{
			$propertyData = $document->{"get" . ucfirst($propertyInfo->getName())}();
		}
		
		if ($propertyData === null)
		{
			return null;
		}
		return strval($propertyData);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 */
	public function exportReadOnlyFieldsData($document, $propertiesName)
	{
		$model = $document->getPersistentModel();
		$datas = array();
		foreach ($propertiesName as $propertyName)
		{
			
			$propertyInfo = $model->getEditableProperty($propertyName);
			if ($propertyInfo)
			{
				$propertyVal = $this->exportReadOnlyProperty($document, $propertyInfo, self::getListId($model, $propertyName));
			}
			else
			{
				$propertyVal = $this->exportROByGetter($document, $propertyName);
			}
			if ($propertyVal !== null)
			{
				$datas[$propertyName] = $propertyVal;
			}
		}
		return $datas;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param PropertyInfo $propertyInfo
	 */
	private function exportReadOnlyProperty($document, $propertyInfo, $listId)
	{
		$propertyData = null;
		if ($propertyInfo->isDocument())
		{
			return null;
		}
		
		if ($propertyInfo->getType() == f_persistentdocument_PersistentDocument::PROPERTYTYPE_BOOLEAN)
		{
			$v = $document->{"get" . ucfirst($propertyInfo->getName())}() ? 'yes' : 'no';
			$propertyData = LocaleService::getInstance()->transBO('m.uixul.bo.general.' . $v, array('ucf'));
		}
		else
		{
			$uigetter = "getUI" . ucfirst($propertyInfo->getName());
			if (f_util_ClassUtils::methodExists($document, $uigetter))
			{
				$propertyData = $document->{$uigetter}();
			}
			else
			{
				$propertyData = $document->{"get" . ucfirst($propertyInfo->getName())}();
			}
		}
		
		
		
		if ($propertyData === null)
		{
			return null;
		}
		if ($listId)
		{
			$list = list_ListService::getInstance()->getByListId($listId);
			if ($list)
			{
				$item = $list->getItemByValue($propertyData);
				if ($item)
				{
					return $item->getLabel();
				}
			}
			else
			{
				Framework::fatal(__METHOD__ . ':' . $listId);
			}
			return null;
		}
		return strval($propertyData);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param array<string, string>
	 */
	public function importFieldsData($document, $propertiesValue)
	{
		$models = $document->getPersistentModel();
		foreach ($propertiesValue as $propertyName => $propertyVal)
		{
			if ($propertyVal === '')
			{
				$propertyVal = null;
			}
			
			$propertyInfo = $models->getEditableProperty($propertyName);
			if ($propertyInfo)
			{
				$this->importProperty($document, $propertyInfo, $propertyVal);
			}
			else
			{
				$this->importBySetter($document, $propertyName, $propertyVal);
			}
		}
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param string $propertyName
	 * @param string $propertyVal
	 */
	private function importBySetter($document, $propertyName, $propertyVal)
	{
		$setter = 'set' . ucfirst($propertyName);
		if (f_util_ClassUtils::methodExists($document, $setter))
		{
			$document->{$setter}($propertyVal);
		}
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param PropertyInfo $propertyInfo
	 * @param string $propertyVal
	 */
	private function importProperty($document, $propertyInfo, $propertyVal)
	{
		$setter = 'set' . ucfirst($propertyInfo->getName());
		$propertyData = $propertyVal;
		
		if ($propertyInfo->isDocument())
		{
			if ($propertyInfo->isArray())
			{
				$propertyData = array();
				$ids = ($propertyVal === null) ? array() : explode(',', $propertyVal);
				foreach ($ids as $id)
				{
					try
					{
						$propertyData[] = DocumentHelper::getDocumentInstance($id);
					}
					catch (Exception $e)
					{
						Framework::exception($e);
					}
				}
				$setter .= 'Array';
			}
			else
			{
				if ($propertyVal !== null)
				{
					try
					{
						$propertyData = DocumentHelper::getDocumentInstance($propertyVal);
					}
					catch (Exception $e)
					{
						Framework::exception($e);
						$propertyData = null;
					}
				}
			}
		}
		else
		{
			switch ($propertyInfo->getType())
			{
				case f_persistentdocument_PersistentDocument::PROPERTYTYPE_BOOLEAN :
					$propertyData = ($propertyVal === 'true');
					break;
				case f_persistentdocument_PersistentDocument::PROPERTYTYPE_DATETIME :
					$setter = 'setUI' . ucfirst($propertyInfo->getName());
					break;
				case f_persistentdocument_PersistentDocument::PROPERTYTYPE_INTEGER :
					if ($propertyVal !== null)
					{
						$propertyData = intval($propertyVal);
					}
					break;
				case f_persistentdocument_PersistentDocument::PROPERTYTYPE_DOUBLE :
					if ($propertyVal !== null)
					{
						$propertyData = floatval($propertyVal);
					}
					break;
				case f_persistentdocument_PersistentDocument::PROPERTYTYPE_XHTMLFRAGMENT :
					if ($propertyVal !== null)
					{
						$propertyData = website_XHTMLCleanerHelper::clean($propertyVal);
					}
					break;
			}
		}
		$document->{$setter}($propertyData);
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @return array
	 */
	public function getPublicationInfos($document)
	{
		$result = array();
		$ls = LocaleService::getInstance();
		$master = DocumentHelper::getByCorrection($document);
		$model = $master->getPersistentModel();
		$useCorrection = $model->useCorrection();
		$localized = $master->isLocalized();
		$vo = $master->getLang();
		
		$result['id'] = $master->getId();
		$result['lang'] = $vo;
		$result['documentversion'] = $master->getDocumentversion();
		$i18nSynchro = $ls->getI18nSynchroForDocument($master);
		$result['useI18nSynchroState'] = count($i18nSynchro['config']) > 0;
		$result['i18nSynchroState'] = $i18nSynchro['action'];
		
		$rc = RequestContext::getInstance();
		if ($localized)
		{
			$result['localized'] = true;
			
			$langs = $master->getI18nInfo()->getLangs();
			foreach ($langs as $lang)
			{
				try
				{
					$rc->beginI18nWork($lang);
					$correction = ($useCorrection) ? $this->getCorrectionDocument($master) : $master;
					$result[$lang] = $this->getPublicationInfosForLang($correction, $model, $lang, $localized, $vo);
					if (isset($i18nSynchro['states'][$lang]))
					{
						$result[$lang]['synchrostate'] = $i18nSynchro['states'][$lang];
						if ($result[$lang]['synchrostate']['from'])
						{
							$lg = $ls->transBO('m.uixul.bo.languages.' . $result[$lang]['synchrostate']['from'], array('ucf'));
							$result[$lang]['title'] = $ls->transBO('m.uixul.bo.doceditor.synchrofrom', array('ucf'), array('to' => $result[$lang]['langlabel'], 'from' => $lg));
						}
					}
					else
					{
						$result[$lang]['synchrostate'] = array('status' => 'UNDEFINED', 'from' => null);
					}
					$rc->endI18nWork();
				}
				catch (Exception $e)
				{
					$rc->endI18nWork($e);
				}
			}
		}
		else
		{
			$result['localized'] = false;
			$lang = $master->getLang();
			try
			{
				$rc->beginI18nWork($lang);
				$correction = ($useCorrection) ? $this->getCorrectionDocument($master) : $master;
				$result[$lang] = $this->getPublicationInfosForLang($correction, $model, $lang, $localized, $vo);
				$rc->endI18nWork();
			}
			catch (Exception $e)
			{
				$rc->endI18nWork($e);
			}
		}
		return $result;
	}
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @return f_persistentdocument_PersistentDocument
	 */
	private function getCorrectionDocument($document)
	{
		$correctionId = intval($document->getCorrectionid());
		if ($correctionId > 0)
		{
			return DocumentHelper::getDocumentInstance($correctionId);
		}
		return $document;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @param string $lang
	 * @param boolean $localized
	 * @param string vo
	 */
	private function getPublicationInfosForLang($document, $model, $lang, $localized, $vo)
	{
		$ls = LocaleService::getInstance();
		$result = array('id' => $document->getId(), 
			'label' => $document->getLabel(), 
			'publicationstatus' => $document->getPublicationstatus(), 
			'publicationstatuslocalized' => $ls->transBO(DocumentHelper::getStatusLocaleKey($document), array('ucf'))
		);
		
		$publicationstatusinfos = $document->getDocumentService()->getUIActivePublicationStatusInfo($document, $lang);
		if ($publicationstatusinfos)
		{
			$result['publicationstatusinfo'] = $publicationstatusinfos;
		}
		
		if ($localized)
		{
			$result['langlabel'] = $ls->transBO('m.uixul.bo.languages.' . $lang, array('ucf'));
			if ($lang == $vo)
			{
				
				$result['deletelabel'] = $ls->transBO('m.uixul.bo.actions.delete', array('ucf'));
				$result['title'] = $ls->transBO('m.uixul.bo.doceditor.vo-state', array('ucf'), array('lang' => $result['langlabel']));
			}
			else
			{
				$result['deletelabel'] = $ls->transBO('m.uixul.bo.actions.delete-translation', array('ucf'));
				$result['title'] = $ls->transBO('m.uixul.bo.doceditor.translation-state', array('ucf'), array('lang' => $result['langlabel']));
			}
		}
		else
		{
			$result['deletelabel'] = $ls->transBO('m.uixul.bo.actions.delete', array('ucf'));
			$result['title'] = $ls->transBO('m.uixul.bo.doceditor.document-state', array('ucf'));
		}
		
		if ($model->useCorrection() && $document->getCorrectionofid())
		{
			$result['correctionof'] = $document->getCorrectionofid();
		}
		
		if ($model->publishOnDayChange())
		{
			$result['usedate'] = true;
			$start = date_DateFormat::format($document->getUIStartpublicationdate(), 'd/m/Y H:i');
			$end = date_DateFormat::format($document->getUIEndpublicationdate(), 'd/m/Y H:i');
			if ($start != '' && $end != '')
			{
				$result['publicationdate'] = $ls->transBO('m.uixul.bo.doceditor.period-between', array('ucf'), array('start' => $start, 'end' => $end));
			}
			else if ($start != '')
			{
				$result['publicationdate'] = $ls->transBO('m.uixul.bo.doceditor.period-starting', array('ucf'), array('start' => $start));
			}
			else if ($end != '')
			{
				$result['publicationdate'] = $ls->transBO('m.uixul.bo.doceditor.period-until', array('ucf'), array('end' => $end)); //'Jusqu\'au ' .$end;
			}
			else
			{
				$result['publicationdate'] = $ls->transBO('m.uixul.bo.doceditor.period-always', array('ucf'));
			}
		}
		else
		{
			$result['publicationdate'] = $ls->transBO('m.uixul.bo.doceditor.period-not-available', array('ucf'));
		}
		
		if ($model->hasWorkflow())
		{
			
			$result['useworkflow'] = workflow_ModuleService::getInstance()->hasPublishedWorkflowByModel($model);
			$info = workflow_WorkitemService::getInstance()->createQuery()->add(Restrictions::eq('documentid', $document->getId()))->add(Restrictions::eq('lang', $lang))->add(Restrictions::published())->find();
			
			if (count($info) > 0)
			{
				
				$workItem = $info[0];
				$result['workitemlabel'] = $workItem->getTransition()->getDescription();
				$result['workitemdate'] = date_DateFormat::format($workItem->getUICreationdate(), 'd/m/Y H:i');
				
				$userTask = task_UsertaskService::getInstance()->createQuery()->add(Restrictions::published())->add(Restrictions::eq('workitem', $workItem))->add(Restrictions::eq('user', users_UserService::getInstance()->getCurrentBackEndUser()))->findUnique();
				if ($userTask)
				{
					$result['taskid'] = $userTask->getId();
					$result['tasklabel'] = $workItem->getLabel();
					$result['taskcommentary'] = $userTask->getCommentary();
					$result['taskdialog'] = $userTask->getDialogName();
				}
			}
		}
		
		return $result;
	}
	
	//Compile Configuration functions
	
	
	/**
	 * @param String $moduleName
	 * @param String $documentName
	 * @return Boolean
	 */
	private function hasDocumentEditor($moduleName, $editorFolderName)
	{
		$path = FileResolver::getInstance()->setPackageName('modules_' . $moduleName)
			->setDirectory('forms/editor')->getPath($editorFolderName);
		return $path !== null;
	}
	
	public function compileEditorsConfig()
	{
		$ms = ModuleService::getInstance();
		$preferences = array();
		
		$editModulesByModelName = array();
		
		$injections = Framework::getConfigurationValue('injection/document', array());
		$injections = array_flip($injections);
		foreach ($ms->getModules() as $package)
		{
			$moduleName = $ms->getShortModuleName($package);
			$configs = array();
			
			if ($moduleName !== 'uixul')
			{
				$editors = $this->getEditorsFolderName($moduleName);
				foreach ($editors as $editorFolderName)
				{
					$config = $this->compileEditorConfig($moduleName, $editorFolderName);
					if ($config)
					{
						list(, $modelName) = explode('_', $config['modelName']);
						if (isset($injections[$modelName]))
						{
							$modelName = $injections[$modelName];
						}
						$editModulesByModelName['modules_' . $modelName] = $moduleName;
						if ($editorFolderName === 'preferences')
						{
							$preferences[$moduleName] = $config;
						}
						elseif ($moduleName  === 'preferences')
						{
							$preferences[$editorFolderName] = $config;
						}
						else 
						{
							$configs[$editorFolderName] = $config;
						}
					}
				}
			}
			
			$compiledFilePath = f_util_FileUtils::buildChangeBuildPath('modules', $moduleName, 'forms', 'editors.php');
			f_util_FileUtils::writeAndCreateContainer($compiledFilePath, serialize($configs), f_util_FileUtils::OVERRIDE);
		}

		$compiledFilePath = f_util_FileUtils::buildChangeBuildPath('modules', 'preferences', 'forms', 'editors.php');
		f_util_FileUtils::writeAndCreateContainer($compiledFilePath, serialize($preferences), f_util_FileUtils::OVERRIDE);
		
		$editModulesByModelNamePath = f_util_FileUtils::buildChangeBuildPath("editModulesByModelName.php");
		f_util_FileUtils::writeAndCreateContainer($editModulesByModelNamePath, serialize($editModulesByModelName), f_util_FileUtils::OVERRIDE);
	}
	
	/**
	 * @var array
	 */
	private $editModulesByModelName = null;
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @return String
	 */
	public function getEditModuleName($document)
	{
		if ($document instanceof generic_persistentdocument_folder)
		{
			return generic_RootfolderService::getInstance()->getModuleNameById($document->getTreeId());
		}
		$modelName = $document->getDocumentModelName();
		if (f_util_StringUtils::endsWith($modelName, "/preferences"))
		{
			return "preferences";
		}
		if ($this->editModulesByModelName === null)
		{
			$path = f_util_FileUtils::buildChangeBuildPath("editModulesByModelName.php");
			$this->editModulesByModelName = unserialize(f_util_FileUtils::read($path));
		}
		if (isset($this->editModulesByModelName[$modelName]))
		{
			return $this->editModulesByModelName[$modelName];
		}
		
		$ancestors = $document->getPersistentModel()->getAncestorModelNames();
		foreach (array_reverse($ancestors) as $modelName) 
		{
			if (isset($this->editModulesByModelName[$modelName]))
			{
				return $this->editModulesByModelName[$modelName];
			}
		}
		return null;
	}
	
	/**
	 * @param unknown_type $documentId
	 * @return String
	 */
	public function getEditModuleNameById($documentId)
	{
		return $this->getEditModuleName(DocumentHelper::getDocumentInstance($documentId));
	}
	
	private function getEditorsFolderName($moduleName)
	{
		$result = array();
		$paths = FileResolver::getInstance()->setPackageName('modules_' . $moduleName)
				->setDirectory('forms/editor')->getPaths('');
		if ($paths)
		{
			foreach ($paths as $path)
			{
				$dirs = scandir($path);
				foreach ($dirs as $dir)
				{
					if (strpos($dir, '.') !== false)
					{
						continue;
					}
					$result[$dir] = array();
				}
			}
		}
		return array_keys($result);
	}
	
	private function compileEditorConfig($moduleName, $editorFolderName, $ignoreHidden = false)
	{
		$panels = array();
		$modelName = null;
		$panelsPath = $this->getEditorXmlConfigPath($moduleName, $editorFolderName, 'panels');
		if ($panelsPath)
		{
			$panelsDoc = f_util_DOMUtils::fromPath($panelsPath);
			if (!$ignoreHidden && $panelsDoc->documentElement->hasAttribute('hidden'))
			{
				return null;
			}
			
			if ($panelsDoc->documentElement->hasAttribute('module'))
			{
				$module = $panelsDoc->documentElement->getAttribute('module');
				$config = $this->compileEditorConfig($module, $editorFolderName, true);
				if ($config)
				{
					$config['bindingModuleName'] = $moduleName;
				}
				return $config;			
			}
			
			$multiLangEnabled = RequestContext::getInstance()->isMultiLangEnabled();
			$modelName = $panelsDoc->documentElement->getAttribute('modelname');
			$plist = $panelsDoc->getElementsByTagName('panel');
			foreach ($plist as $panel)
			{
				$pn = $panel->getAttribute('name');
				if (!$multiLangEnabled && $pn === 'localization')
				{
					continue;
				}
				$val = array();
				if ($panel->hasAttribute('labeli18n'))
				{
					$val['labeli18n'] = $panel->getAttribute('labeli18n');
				}
				if ($panel->hasAttribute('icon'))
				{
					$val['icon'] = $panel->getAttribute('icon');
				}
				$panels[$pn] = count($val)? $val : true;
			}
		}
		
		if (f_util_StringUtils::isEmpty($modelName))
		{
			switch ($editorFolderName)
			{
				case 'rootfolder' :
				case 'systemfolder' :
				case 'folder' :
					$modelName = 'modules_generic/' . $editorFolderName;
					break;
				case 'topic' :
				case 'systemtopic' :
				case 'websitetopicsfolder' :
					$modelName = 'modules_website/' . $editorFolderName;
					break;
				default :
					$modelName = 'modules_' . $moduleName . '/' . $editorFolderName;
					break;
			}
		}
		
		$result = array('moduleName' => $moduleName, 'editorFolderName' => $editorFolderName);
		try
		{
			$result['modelName'] = $modelName;
			$model = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($modelName);
			if (count($panels) == 0)
			{
				$panels = $this->getDefaultPanels($model);
			}
			$result['panels'] = $panels;
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			return null;
		}
		return $result;
	}
	
	private function getEditorXmlConfigPath($moduleName, $editorFolderName, $configName)
	{
		return FileResolver::getInstance()->setPackageName('modules_' . $moduleName)
			->setDirectory('forms/editor/' . $editorFolderName)->getPath($configName . '.xml');
	}

	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 * @return array
	 */
	private function getDefaultPanels($model)
	{
		$panels = array('resume' => true);
		
		switch ($model->getName())
		{
			case 'modules_generic/rootfolder' :
			case 'modules_generic/systemfolder' :
				$panels = array('resume' => true, 'publication' => true, 'permission' => true, 'history' => true);
				break;
			case 'modules_generic/folder' :
				$panels = array('resume' => true, 'properties' => true, 'publication' => true, 'permission' => true, 'history' => true, 'create' => true);
				break;
			case 'modules_website/topic' :
			case 'modules_website/systemtopic' :
				$panels = array('resume' => true, 'permission' => true);
				break;
			case 'modules_generic/websitetopicsfolder' :
				$panels = array('resume' => true);
				break;
			default :
				$panels = array('resume' => true, 'properties' => true);
				if ($model->isLocalized() && RequestContext::getInstance()->isMultiLangEnabled())
				{
					$panels['localization'] = true;
				}
				$panels['publication']  = true;
				if ($model->useRewriteURL())
				{
					$panels['redirect'] = true;
				}
				$panels['history']  = true;
				$panels['create']  = true;
		}
		return $panels;
	}
	
	public function generateDocumentEditor($moduleName, $documentName, $panels)
	{
		$basePath = f_util_FileUtils::buildWebeditPath('modules', $moduleName, 'forms', 'editor', $documentName);
		if (!file_exists($basePath))
		{
			f_util_FileUtils::mkdir($basePath);
			echo $basePath . " generated\n";	
		}
		
		if (count($panels) > 0)
		{
			$model = $this->estimateModel($moduleName, $documentName);
			if ($model === null)
			{
				echo "model undefined\n";
				return;
			}
			
			foreach ($panels as $panelName)
			{
				$panelPath = f_util_FileUtils::buildAbsolutePath($basePath, $panelName . '.xml');
				if (file_exists($panelPath)) 
				{
					echo "$panelName already exists\n";
					continue;
				}
								
				switch ($panelName) 
				{
					case 'panels':
						$model = $this->estimateModel($moduleName, $documentName);
						$panelsDoc = f_util_DOMUtils::fromString('<panels />');						
						foreach (array_keys($this->getDefaultPanels($model)) as $n)
						{
							$panel = $panelsDoc->createElement('panel');
							$panel->setAttribute('name', $n);
							$panelsDoc->documentElement->appendChild($panel);
						}
						f_util_DOMUtils::save($panelsDoc, $panelPath);	
						echo $panelPath . " generated\n";			
					break;							
					case 'create':
					case 'properties':
					case 'history':
					case 'publication':						
					case 'resume':
					case 'permission':
					case 'localization':
						$defPath = $this->getPanelDefinitionPath($panelName, $moduleName, $documentName);
						if ($defPath === null)
						{
							echo "Could not build panel $panelName for $moduleName/$documentName";
						}
						$content = file_get_contents($defPath);
						if ($panelName !== 'publication')
						{
							$tr = uixul_lib_DocumentEditorPanelTagReplacer::getInstance($panelName, $moduleName, $model->getName());
							$panelDefDoc = f_util_DOMUtils::fromString($tr->run($content));
						}
						else
						{
							$panelDefDoc = f_util_DOMUtils::fromString($content);
						}
						f_util_DOMUtils::save($panelDefDoc, $panelPath);
						echo "$panelPath generated\n";							
					break;
				}
			}
		}
	}
	
	/**
	 * @param string $moduleName
	 * @param string $documentName
	 */
	private function estimateModel($moduleName, $documentName)
	{
		switch ($documentName)
		{
			case 'rootfolder' :
			case 'systemfolder' :
			case 'folder' :
				return f_persistentdocument_PersistentDocumentModel::getInstance('generic', $documentName);
			case 'systemtopic' :
			case 'websitetopicsfolder' :
			case 'topic' :
				return f_persistentdocument_PersistentDocumentModel::getInstance('website', $documentName);
			default :
				if (f_persistentdocument_PersistentDocumentModel::exists('modules_' . $moduleName . '/' . $documentName))
				{
					return f_persistentdocument_PersistentDocumentModel::getInstance($moduleName, $documentName);
				}
				break;
		}
		return null;
	}
}