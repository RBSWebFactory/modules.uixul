<?php
class commands_GenerateDocumentEditor extends c_ChangescriptCommand
{
	private static $options = array("resume", "properties", "publication", "localization", "history", "create", "permission", "panels");
	 
	/**
	 * @return string
	 */
	function getUsage()
	{
		return "<moduleName[/documentName]> [element = create|history|localization|panels|properties|publication|resume|permission]";
	}

	function getAlias()
	{
		return "gde";
	}

	/**
	 * @return string
	 */
	function getDescription()
	{
		return "generate document editor";
	}

	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		$paramsCount = count($params);
		if ($paramsCount == 1)
		{
			return true;
		}
		if ($paramsCount == 2)
		{
			$panels = explode(",", $params[1]);
			return count(array_intersect(self::$options, $panels)) == count($panels);
		}
		return false;
	}

	/**
	 * @param integer $completeParamCount the parameters that are already complete in the command line
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @return string[] or null
	 */
	function getParameters($completeParamCount, $params, $options, $current)
	{
		if ($completeParamCount == 0)
		{
			$this->loadFramework();
			$modelNames = array();
			foreach (f_persistentdocument_PersistentDocumentModel::getDocumentModelNamesByModules() as $module => $names)
			{
				$modelNames[] = $module;
				foreach ($names as $name)
				{
					$modelNames[] = substr($name, 8);
				}
			}
			return $modelNames;
		}
		if ($completeParamCount == 1)
		{
			return self::$options;
		}
	}

	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Generate document editor ==");

		$this->loadFramework();
		list ($moduleName, $documentName) = explode("/", $params[0]);
		
		if (!ModuleService::getInstance()->moduleExists($moduleName))
		{
			return $this->quitError("Invalid module name : " . $moduleName);
		}

		if (isset($params[1]))
		{
			$panels = explode(",", $params[1]);
		}
		else if ($documentName === null)
		{
			$panels = array("perspective");
		}
		else
		{
			$panels = array();
		}

		if ($documentName !== null && !in_array($documentName, array('rootfolder', 'folder', 'systemfolder', 'topic')))
		{
			if (!f_persistentdocument_PersistentDocumentModel::exists("modules_$moduleName/$documentName"))
			{
				return $this->quitError("Invalid document name : " . $moduleName."/$documentName");
			}
		}

		if (($perspectiveIndex = array_search("perspective", $panels)) !== false)
		{
			$this->warnMessage("Deprecated perspective panel...");
			unset($panels[$perspectiveIndex]);
		}

		if ($documentName !== null)
		{
			$this->message("Processing $moduleName/$documentName ".join(", ", $panels)."...");
			uixul_DocumentEditorService::getInstance()->generateDocumentEditor($moduleName, $documentName, $panels);
		}

		$this->quitOk("Document editor generated");
	}
}