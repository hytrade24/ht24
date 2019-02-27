<?php

class Ad_Import_PresetEditor_PresetEditorManagement {

	private static $db;
	private static $instance = null;

	/** @var Ad_Import_Preset_PresetManagement */
	protected $importPresetManagement = null;

	protected $currentStep = 1;
	protected $maxStep = 1;
	/** @var  Ad_Import_Preset_AbstractPreset */
	protected $preset;
	protected $presetType = 'Csv';
	protected $presetId = null;

	protected $steps = array();
	protected $flashMessages = array();

	protected $dontSaveInSession = false;

	const SESSION_NAME = 'EBIZ_TRADER_IMPORT_PRESET_EDITOR';

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return Ad_Import_Preset_PresetEditorManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		$me = self::$instance;
		$me->init();
		$me->loadFromSession();

		return $me;
	}

	protected function init() {
		$this->importPresetManagement = Ad_Import_Preset_PresetManagement::getInstance($this->getDb());
		$this->initSteps();
	}

	protected function initSteps() {
		$this->steps = array(
			1 => new Ad_Import_PresetEditor_Step_TypeStep($this),
			2 => new Ad_Import_PresetEditor_Step_BaseMappingStep($this),
			3 => new Ad_Import_PresetEditor_Step_CategoryMappingStep($this),
			4 => new Ad_Import_PresetEditor_Step_DetailMappingStep($this),
			5 => new Ad_Import_PresetEditor_Step_SettingsStep($this)
		);
	}


	/**
	 * Setzt die Vorlage zurück
	 *
	 * @throws Exception
	 */
	public function reset() {
		$this->destroySession();

		$this->currentStep = 1;
		$this->maxStep = 1;
		$this->maxStep = 1;
		$this->presetId = null;

		$this->preset = $this->importPresetManagement->createNewPreset($this->presetType);
	}

	public function destroy() {
		$this->destroySession();
		$this->dontSaveInSession = true;
	}

	/**
	 * @param Ad_Import_Preset_AbstractPreset|null $preset
	 */
	public function loadPreset($preset) {
		$this->maxStep = count($this->steps);
		$this->preset = $preset;

		$this->presetId = $preset->getImportPresetId();
		$this->presetType = $preset->getPresetType();
	}


	/**
	 * Läd den angegeben Schritt $stepIndex
	 *
	 * @param $stepIndex
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function loadStep($stepIndex) {
		if(!isset($this->steps[$stepIndex]) || !($this->steps[$stepIndex] instanceof Ad_Import_PresetEditor_Step_PresetEditorStepInterface)) {
			throw new Exception("could not find step index $stepIndex ");
		}


		$this->currentStep = $stepIndex;
		return $this->steps[$stepIndex]->load();
	}

	/**
	 * Speichert Daten den aktuellen oder des angegebenen Schrittes, indem es die spezifische Save Methode des Schrittes
	 * ausführt
	 *
	 * @param      $data
	 * @param null $stepIndex
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function saveData($data, $stepIndex = null) {
		global $tpl_main;

		if($stepIndex == null) {
			$stepIndex = $this->currentStep;
		}

		if(!isset($this->steps[$stepIndex]) || !($this->steps[$stepIndex] instanceof Ad_Import_PresetEditor_Step_PresetEditorStepInterface)) {
			throw new Exception("could not find step index $stepIndex ");
		}

		/** @var Ad_Import_PresetEditor_FormResult $result */
		$result = $this->steps[$stepIndex]->save($data);
		if($result->isSuccess()) {
			$this->currentStep++;
			$this->maxStep++;

			return true;
		} else {
			$this->setFlashMessages($result->getErrors());

			return false;
		}
	}

	public function removeCategoryMapping($categoryMappingKey, $stepIndex = null) {

		if($stepIndex == null) {
			$stepIndex = $this->currentStep;
		}

		return $this->steps[$stepIndex]->remove_category_mapping_value($categoryMappingKey);
	}




	public function loadEditorNavigation() {
		global $s_lang;


		$tpl = new Template('tpl/'.$s_lang.'/my-import-presets-edit.nav.htm');
		$tpl->addvar('CURRENT_STEP', $this->currentStep);

        if ($this->getPreset() !== null) {
            $tpl->addvar('MAX_STEP', min($this->maxStep, $this->getPreset()->getStepMax()));
        } else {
            $tpl->addvar('MAX_STEP', $this->maxStep);
        }

		return $tpl->process();
	}

	public function saveState() {
		$this->saveToSession();
	}

	public function __destruct() {
		$this->saveToSession();
	}

	protected function saveToSession() {
		if(!$this->dontSaveInSession) {
			$_SESSION[self::SESSION_NAME] = array(
					"currentStep" => $this->currentStep, "maxStep" => $this->maxStep,
					"preset" => serialize($this->preset), "presetType" => $this->presetType,
					"presetId" => $this->presetId
			);
		}
	}

	protected function loadFromSession() {
		$session = $_SESSION[self::SESSION_NAME];


		if(isset($session)) {
			$this->currentStep = $session['currentStep'];
			$this->maxStep = $session['maxStep'];
			$a = microtime(true);
			#var_dump(memory_get_usage());
			$this->preset = unserialize($session['preset']);

			#var_dump(microtime(true)-$a, memory_get_usage());
			$this->presetType = $session['presetType'];
			$this->presetId = $session['presetId'];
		} else {
			$this->reset();
		}
	}

	protected function destroySession() {
		$_SESSION[self::SESSION_NAME] = null;
	}

	public  function saveToDatabase() {
		global $uid;

		if($this->preset->getOwnerUser() != null & ($uid != $this->preset->getOwnerUser())) {
			throw new Exception("You do not own this preset");
		}


		$this->preset->setOwnerUser($uid);
		$presetId = $this->importPresetManagement->savePreset($this->presetId, $this->preset);

		if($presetId != null) {
			$this->presetId = $presetId;
		}

		return $presetId;
	}

	/**
	 * @return int
	 */
	public function getCurrentStep() {
		return $this->currentStep;
	}

	/**
	 * @param int $currentStep
	 *
	 * @return Ad_Import_PresetEditor_PresetEditorManagement
	 */
	public function setCurrentStep($currentStep) {
		$this->currentStep = $currentStep;

		return $this;
	}

	/**
	 * @return Ad_Import_PresetEditor_Step_AbstractPresetEditorStep
	 */
	public function getCurrentStepRenderer() {
		return $this->steps[$this->getCurrentStep()];
	}

		/**
	 * @return string
	 */
	public function getPresetType() {
		return $this->presetType;
	}

	/**
	 * @param string $presetType
	 *
	 * @return Ad_Import_PresetEditor_PresetEditorManagement
	 */
	public function setPresetType($presetType) {
		$this->presetType = $presetType;

		return $this;
	}

	/**
	 * @return Ad_Import_Preset_AbstractPreset
	 */
	public function getPreset() {
		return $this->preset;
	}

	/**
	 * @return array
	 */
	public function getFlashMessages() {
		return $this->flashMessages;
	}

	/**
	 * @param array $flashMessages
	 *
	 * @return Ad_Import_PresetEditor_PresetEditorManagement
	 */
	public function setFlashMessages($flashMessages) {
		$this->flashMessages = $flashMessages;

		return $this;
	}

	public function resetFlashMessages() {
		$this->flashMessages = array();
	}

	/**
	 * @return int
	 */
	public function getMaxStep() {
		return $this->maxStep;
	}

	/**
	 * @param int $maxStep
	 *
	 * @return Ad_Import_PresetEditor_PresetEditorManagement
	 */
	public function setMaxStep($maxStep) {
		$this->maxStep = $maxStep;

		return $this;
	}


	public function isActiveSession() {
		return ($_SESSION[self::SESSION_NAME] != null);
	}


	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}

	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}

	private function __construct() {
	}

	private function __clone() {
	}
}