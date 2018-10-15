<?php
namespace Vanderbilt\AutoDAGsExternalModule;

class AutoDAGsExternalModule extends \ExternalModules\AbstractExternalModule{
//	const LABEL_VALUE_SEPARATOR = ' - ';

	// We cache group info for the set-all-dags.php script, both for performance and because
	// REDCap::getGroupNames() doesn't pick up on added or renamed groups until the next request.
	private $groupsByID;

	function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance){
		$this->setDAGFromField($project_id, $record, $group_id);
	}

	function setDAGFromField($project_id, $record, $group_id){
		$currentGroupId = !is_null($group_id) ? intval($group_id) : $group_id;
		$dagFieldName = $this->getProjectSetting('dag-field');
		$label_value_separator = ($this->getSystemSetting('label-value-separator')?:' - ');

		if(empty($dagFieldName)){
			return;
		}

		$recordIdFieldName = \REDCap::getRecordIdField();
		$data = json_decode(\REDCap::getData($project_id, 'json', [$record], [$recordIdFieldName, $dagFieldName]))[0];

		$fieldValue = $data->$dagFieldName;

		if(empty($fieldValue)){
			$groupId = null;
		}
		else{
			$fieldLabel = $this->getChoiceLabel($dagFieldName, $fieldValue);

			$groupName = $fieldLabel . $label_value_separator . $fieldValue;

			list($groupId, $existingGroupName) = $this->getDAGInfoForFieldValue($fieldValue);
			if($groupId == null){
				$groupId = $this->createDAG($groupName);
			}
			else if($existingGroupName != $groupName){
				$this->renameDAG($groupId, $groupName);
			}
		}

		$ignorenull = $this->getProjectSetting('ignore-null-value');
		if ($currentGroupId !== $groupId && (!$ignorenull || $groupId !== null)) {
			$this->setDAG($record, $groupId);
		}
	}

	private function getDAGInfoForFieldValue($value){
		if(!isset($this->groupsByID)){
			$this->groupsByID = \REDCap::getGroupNames();
		}

		foreach($this->groupsByID as $groupId=>$groupName){
			$lastSeparatorIndex = strrpos($groupName, $label_value_separator);
			$associatedFieldValue = substr($groupName, $lastSeparatorIndex + strlen($label_value_separator));

			if($associatedFieldValue == $value){
				return [$groupId, $groupName];
			}
		}

		return [null, null];
	}

	public function createDAG($groupName){
		$groupId = parent::createDag($groupName);

		$this->groupsByID[$groupId] = $groupName;

		return $groupId;
	}

	public function renameDAG($groupId, $groupName){
		parent::renameDAG($groupId, $groupName);

		$this->groupsByID[$groupId] = $groupName;
	}
}
