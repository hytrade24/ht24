<?php

abstract class Form_Steps {
    
    protected static $templateBase = "my-steps.htm";
    
    protected static $templateSteps = "my-steps.list.htm";
    protected static $templateStepsRow = "my-steps.list.row.htm";
    
    protected static $templateContent = "my-steps.content.htm";
    
    protected $db;
    protected $arData;
    protected $arSteps;
    
    protected $indexStepLast;
    
    private $sessionIdent;
    
    public function __construct(ebiz_db $db = null) {
        $this->db = ($db === null ? $GLOBALS["db"] : $db);
        $this->arData = array();
        $this->arSteps = array();
        $this->indexStepLast = 0;
        $this->sessionIdent = $this->getSessionIdent();
        $this->init();
    }

    function __destruct() {
        $_SESSION[$this->sessionIdent] = array(
            "indexStepLast" => $this->indexStepLast,
            "data" => $this->arData
        );
    }

    /**
     * Adds a new step
     * @param string $ident
     * @param string $title
     * @param string|Template $templateFile
     * @param array $templateVars
     * @param array $additionalData
     * @return bool
     */
    public function addStep($ident, $title, $templateFile, $templateVars = array(), $additionalData = array()) {
        $this->arSteps[] = array(
            "IDENT" => $ident,
            "TITLE" => $title,
            "TEMPLATE_FILE" => $templateFile,
            "TEMPLATE_VARS" => $templateVars,
            "ADDITIONAL" => $additionalData
        );
        return true;
    }

    /**
     * Initialize the object
     */
    protected function init() {
        if (empty($this->arData) && array_key_exists($this->sessionIdent, $_SESSION)) {
            $this->indexStepLast = $_SESSION[$this->sessionIdent]["indexStepLast"];
            $this->arData = $_SESSION[$this->sessionIdent]["data"];
        }
        $this->reinitSteps();
    }
    
    protected function reinitSteps() {
        /*
         * Steps
         */
        $this->arSteps = array();
        $this->initSteps();
        // Plugin event
        $stepsInitParams = new Api_Entities_EventParamContainer(array(
            "stepsObject" => $this,
            "ident"       => $this->getSessionIdent(),
            "stepsList"   => $this->arSteps
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::STEPS_GENERAL_INIT, $stepsInitParams);
        if ($stepsInitParams->isDirty()) {
            $this->arSteps = $stepsInitParams->getParam("steps");
        }
    }

    /**
     * Get the step with the given index
     * @param int $index
     * @return mixed|null
     */
    protected function getStepByIndex($index) {
        if (($index >= 0) && ($index <= (count($this->arSteps) - 1))) {
            $arStep = $this->arSteps[$index];
            $arStep["INDEX"] = $index;
            return $arStep;
        }
        return null;
    }

    /**
     * Get the step with the given ident
     * @param string $ident
     * @return mixed|null
     */
    protected function getStepByIdent($ident) {
        foreach ($this->arSteps as $stepIndex => $arStep) {
            if ($arStep["IDENT"] == $ident) {
                $arStep["INDEX"] = $stepIndex;
                return $arStep;
            }
        }
        return null;
    }

    /**
     * Gets the full list of steps
     * @return array    List of steps for creating the new ad
     */
    public function getStepList() {
        foreach ($this->arSteps as $indexStep => $arStep) {
            $this->arSteps[$indexStep]["ENABLED"] = ($this->indexStepLast >= $indexStep ? 1 : 0);
        }
        return $this->arSteps;
    }

    /**
     * Handle a request
     * @param $arData
     * @param array $arFiles
     * @return array
     */
    public function handleRequest($arData, $arFiles = array()) {
        switch ($arData["action"]) {
            case "show":
                $arStep = $this->getStepByIdent($arData["step"]);
                return array(
                    "list" => $this->renderStepList($arStep["INDEX"]),
                    "content" => $this->renderStepContent($arStep["INDEX"], $arData)
                );
            case "submit":
                return $this->handleSubmit($arData, $arFiles);
        }
    }

    /**
     * Handle submitting a step
     * @see submitStep
     * @param array $arData
     * @param array $arFiles
     * @return array
     */
    public function handleSubmit($arData, $arFiles = array()) {
        if (array_key_exists("step", $arData)) {
            $arStep = $this->getStepByIdent($arData["step"]);
        } else {
            $arStep = $this->getStepByIndex($this->indexStepLast);
        }
        $arStepNext = $this->getStepByIndex($arStep["INDEX"] + 1);
        $arResponse = array(
            "STEP_NEXT" => ($arStepNext !== null ? $arStepNext["IDENT"] : null),
            "REDIRECT_URL" => ($arStepNext !== null ? null : $GLOBALS["tpl_content"]->tpl_uri_action($this->getUrlFinishIdent())),
            "ERRORS" => array(), "EXTRAS" => array()
        );
        $continue = $this->submitStep($arStep, $arData, $arFiles, $arResponse);
        
        // Plugin event
        $stepSubmitParams = new Api_Entities_EventParamContainer(array(
            "stepsObject"       => $this,
            "ident"             => $this->getSessionIdent(),
            "step"              => $arStep,
            "data"              => $this->arData,
            "dataRequest"       => $arData,
            "files"             => $arFiles,
            "continue"          => $continue,
            "response"          => $arResponse
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::STEPS_GENERAL_SUBMIT, $stepSubmitParams );
        if ($stepSubmitParams->isDirty()) {
            $this->arData = $stepSubmitParams->getParam("data");
            $continue = $stepSubmitParams->getParam("continue");
            $arResponse = $stepSubmitParams->getParam("response");
        }
        
        if ($continue) {
            if (array_key_exists("stepNext", $arData)) {
                $arStepNext = $this->getStepByIdent($arData["stepNext"]);
                $arResponse["STEP_NEXT"] = $arData["stepNext"];
            }
            if (array_key_exists("stepFinish", $arData) && $arData["stepFinish"]) {
                $arStepNext = null;
                $arResponse["STEP_NEXT"] = null;
                $arResponse["REDIRECT_URL"] = $GLOBALS["tpl_content"]->tpl_uri_action($this->getUrlFinishIdent());
            }
            if ($arStepNext === null) {
                // Done! Save to db
                $this->saveToDatabase();
            } else {
                // Continue to next step
                if ($this->indexStepLast < $arStepNext["INDEX"]) {
                    $this->indexStepLast = $arStepNext["INDEX"];
                }
            }
        } else {
            $arResponse["STEP_NEXT"] = $arStep["IDENT"];
        }
        if ($arResponse["STEP_NEXT"] !== null) {
            $arResponse["REDIRECT_URL"] = null;
        }
        return $arResponse;
    }
    
    public function render($stepActive, $templateBase = null) {
        global $s_lang;
        $arStep = $this->getStepByIndex($stepActive);
        $templateFile = "tpl/".$s_lang."/".($templateBase === null ? self::$templateBase : $templateBase);
        $tpl_base = new Template($templateFile);
        if ($arStep !== null) {
            $tpl_base->addvars($arStep, "STEP_ACTIVE_");
        }
        $tpl_base->addvar("URL_IDENT", $this->getUrlIdent());
        $tpl_base->addvar("STEP_ACTIVE", $stepActive);
        $tpl_base->addvar("STEPS_LIST", $this->renderStepList($stepActive));
        $tpl_base->addvar("STEPS_CUR", $this->renderStepContent($stepActive));
        $scripts = $this->getScriptIncludes();
        
        // Plugin event
        $stepsScriptsParams = new Api_Entities_EventParamContainer(array(
            "stepsObject" => $this,
            "ident"       => $this->getSessionIdent(),
            "scripts"     => $scripts
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::STEPS_GENERAL_SCRIPTS, $stepsScriptsParams);
        if ($stepsScriptsParams->isDirty()) {
            $scripts = $stepsScriptsParams->getParam("scripts");
        }
        
        if (!empty($scripts)) {
            $tpl_base->addvar("SCRIPTS", $scripts);
        }
        return $tpl_base->process();
    }
    
    /**
     * Renders the list of steps for creating an article.
     * @param bool|string   $stepActive     Numeric index or ident of the step to be rendered active (false if none)
     * @param bool|string   $templateBase   Template to be used rendering the whole list (false for default)
     * @param bool|string   $templateRow    Template to be used rendering each item (false for default)
     * @return string       The final rendered step list as HTML code.
     */
    public function renderStepList($stepActive, $templateSteps = null, $templateStepsRow = null) {
        global $s_lang;
        // Templates
        $templateSteps = "tpl/".$s_lang."/".($templateSteps === null ? self::$templateSteps : $templateSteps);
        $templateStepsRow = "tpl/".$s_lang."/".($templateStepsRow === null ? self::$templateStepsRow : $templateStepsRow);
        // Render step list
        $arSteps = $this->getStepList($stepActive);
        $tpl_list = new Template($templateSteps);
        $tpl_list->addvar("STEP_ACTIVE", $stepActive);
        $tpl_list->addlist("liste", $arSteps, $templateStepsRow);
        return $tpl_list->process();
    }
    

    /**
     * Renders the content of the given step for creating an article.
     * @param int|string    $stepActive     Numeric index or ident of the step to be rendered active (false if none)
     * @param array         $tplVars        Variables to be passed to the step's template
     * @return string       The final rendered step list as HTML code.
     */
    public function renderStepContent($stepActive, $arData = array(), $templateContent = null, $templateVars = array()) {
        global $s_lang, $nar_systemsettings;
        $arStep = $this->getStepByIndex($stepActive);
        $arStepPrev = $this->getStepByIndex($stepActive-1);
        $arStepNext = $this->getStepByIndex($stepActive+1);
        $templateFile = "tpl/".$s_lang."/".($templateContent === null ? self::$templateContent : $templateContent);
        $tpl_step = new Template($templateFile); 
        $tpl_step->addvar("URL_IDENT", $this->getUrlIdent());
        $tpl_step->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
        $tpl_step->addvar("STEP_INDEX", $stepActive);
        $tpl_step->addvar("STEP_IDENT", $arStep["IDENT"]);
        $tpl_step->addvar("STEP_TITLE", $arStep["TITLE"]);
        if ($arStepPrev !== null) {
            $tpl_step->addvar("STEP_PREV_INDEX", $stepActive-1);
            $tpl_step->addvar("STEP_PREV_IDENT", $arStepPrev["IDENT"]);
        }
        if ($arStepNext !== null) {
            $tpl_step->addvar("STEP_NEXT_INDEX", $stepActive+1);
            $tpl_step->addvar("STEP_NEXT_IDENT", $arStepNext["IDENT"]);
        } else {
            $tpl_step->addvar("STEP_FINISH", 1);
        }
        if ($this->indexStepLast >= (count($this->arSteps) - 1)) {
            $tpl_step->addvar("STEP_FINISH", 1);
        }
        $tpl_step->addvar("OBJECT_TITLE", $this->getObjectTitle());
        $tpl_step->addvars($arStep["TEMPLATE_VARS"]);
        $tpl_step->addvars($templateVars);
        if ($arStep["TEMPLATE_FILE"] instanceof Template) {
            $tpl_step_content = $arStep["TEMPLATE_FILE"];
        } else {
            $tpl_step_content = new Template("tpl/".$s_lang."/".$arStep["TEMPLATE_FILE"]);
        }
        $tpl_step->addvar("CONTENT", $tpl_step_content);
        $this->prepareStepContent($arStep, $arData, $tpl_step_content, $tpl_step);
        
        // Plugin event
        $stepSubmitParams = new Api_Entities_EventParamContainer(array(
            "stepsObject"       => $this,
            "ident"             => $this->getSessionIdent(),
            "step"              => $arStep,
            "data"              => $this->arData,
            "dataRequest"       => $arData,
            "templateContent"   => $tpl_step_content,
            "templateStep"      => $tpl_step
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::STEPS_GENERAL_RENDER, $stepSubmitParams );
        
        return $tpl_step->process();
    }

    /**
     * Called to load an object from the database
     * @param int $id
     * @return mixed
     */
    public function loadFromDatabase($id) {
        $this->loadEntityFromDatabase($id);
        
        // Plugin event
        $stepLoadParams = new Api_Entities_EventParamContainer(array(
            "stepsObject"       => $this,
            "ident"             => $this->getSessionIdent(),
            "data"              => $this->arData,
            "indexStepLast"     => $this->indexStepLast
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::STEPS_GENERAL_DB_LOAD, $stepLoadParams );
        if ($stepLoadParams->isDirty()) {
            $this->arData = $stepLoadParams->getParam("data");
            $this->indexStepLast = $stepLoadParams->getParam("indexStepLast");
        }
        
        // Update steps
        $this->init();
        
    }

    /**
     * Called when finally saving the object to the database
     * @return mixed
     */
    public function saveToDatabase() {
        $result = $this->saveEntityToDatabase();
        
        // Plugin event
        $stepSaveParams = new Api_Entities_EventParamContainer(array(
            "stepsObject"       => $this,
            "ident"             => $this->getSessionIdent(),
            "data"              => $this->arData,
            "result"            => $result
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::STEPS_GENERAL_DB_SAVE, $stepSaveParams );
        if ($stepSaveParams->isDirty()) {
            $this->arData = $stepSaveParams->getParam("data");
            $result = $stepSaveParams->getParam("result");
        }
        
        return $result;
    }

    /**
     * Initialize the available steps
     */
    protected abstract function initSteps();

    /**
     * Return the title for the current object
     * @return string
     */
    protected abstract function getObjectTitle();

    /**
     * Return the ident that is used to save the information inbetween steps
     * @return string
     */
    protected abstract function getSessionIdent();

    /**
     * Return javascript/css/... includes that should be loaded
     * @return string
     */
    protected abstract function getScriptIncludes();

    /**
     * Return the nav ident for the page that will handle the steps
     * @return string
     */
    protected abstract function getUrlIdent();

    /**
     * Return the nav ident for the page that will be shown after completing the process
     * @return string
     */
    protected abstract function getUrlFinishIdent();

    /**
     * Will be called before rendering the content of a step
     * @param array $arStep
     * @param array $arData
     * @param Template $tpl_content
     * @param Template $tpl_step
     * @return mixed
     */
    protected abstract function prepareStepContent($arStep, $arData, Template $tpl_content, Template $tpl_step);

    /**
     * Will be called after submitting a step
     * @param array $arStep
     * @param array $arData
     * @param array $arFiles
     * @param array $arResponse
     * @return mixed
     */
    protected abstract function submitStep($arStep, $arData, $arFiles, &$arResponse);

    /**
     * Called to load an object from the database
     * @param int $id
     * @return mixed
     */
    protected abstract function loadEntityFromDatabase($id);

    /**
     * Called when finally saving the object to the database
     * @return mixed
     */
    protected abstract function saveEntityToDatabase();
    
} 

?>