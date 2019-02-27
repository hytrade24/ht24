<?php

class Api_Plugins_Leads_Plugin extends Api_TraderApiPlugin {

    private $dbLeads;
    private $leadsUser;
    
    function __construct(Api_TraderApiHandler $apiHandler, $pluginBasePath = NULL)
    {
        parent::__construct($apiHandler, $pluginBasePath);
        /**
         * Leads database credentials
         * @var string $db_name
         * @var string $db_host
         * @var string $db_user
         * @var string $db_pass
         */
        include __DIR__."/inc.server.php";
        $this->dbLeads = new ebiz_db($db_name, $db_host, $db_user, $db_pass, true);
        $this->leadsUser = null;
    }

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent(Api_TraderApiEvents::AJAX_PLUGIN, "ajaxPlugin");
        $this->registerEvent(Api_TraderApiEvents::USER_REGISTER_CHECK, "userRegisterCheck");
        $this->registerEvent(Api_TraderApiEvents::USER_NEW, "userNew");
        $this->registerEvent(Api_TraderApiEvents::USER_DELETE, "userDelete");
        $this->registerEvent(Api_TraderApiEvents::USER_PROFILE_CHECK, "userProfileCheck");
        $this->registerEvent(Api_TraderApiEvents::USER_PROFILE_CHANGE, "userProfileChange");
        $this->registerEvent(Api_TraderApiEvents::ADMIN_WELCOME_TODO, "adminWelcomeTodo");
        $this->registerEvent(Api_TraderApiEvents::TEMPLATE_PLUGIN_FUNCTION, "templateFunction");
        return true;
    }
    
    public function ajaxPlugin(Api_Entities_EventParamContainer $params) {
        $action = $params->getParam("action");
        $jsonResult = array("success" => false);
        switch ($action) {
            case 'article_lead':
                $jsonResult["success"] = $this->leadCreate(
                    $_POST["ID_AD"], null, null, $_POST["DATE_DUE"], $_POST["QUANTITY"]
                );
                break;
        }
        header("Content-Type: application/json");
        die(json_encode($jsonResult));
    }
    
    public function adminWelcomeTodo(Api_Entities_EventParamContainer $params) {
        $countModerate = $this->queryLeadsCount(["moderated" => false, "declined" => false]);
        if ($countModerate > 0) {
            $htmlTrans = Translation::readTranslationRaw(
                "marktplatz", "leads.admin.moderate", null, [],
                "{count} <strong>Leads</strong> warten auf Bestätigung"
            );
            $htmlNotify = "
                <tr>
                    <td><img src=\"gfx/warnung.png\"></td>
                    <td>
                        <a href=\"index.php?page=leads&moderated=0\">
                            ".str_replace("{count}", $countModerate, $htmlTrans)."
                        </a>
                    </td>
                </tr>";
            $params->setParamArrayAppend("list", $htmlNotify);
        }
    }
    
    public function countProjects() {
        return $this->dbLeads->fetch_atom("SELECT count(*) FROM `projects` WHERE `valid_until` > NOW()");
    }
    
    public function countLeads() {
        return $this->dbLeads->fetch_atom("SELECT count(*) FROM `leads` WHERE `status` = 'public'");
    }
    
    public function login($traderUserId, $employeeId = null) {
        require_once $GLOBALS["ab_path"]."/inc.laravel.php";
        if ($employeeId > 0) {
            $userQuery = \Plugins\Hydromot\User::where("id", "=", $employeeId);
        } else {
            $userQuery = \Plugins\Hydromot\User::whereTraderId($traderUserId);
        }
        if ($userQuery->count() > 0) {
            $user = $userQuery->first();
            
            $request = \Illuminate\Http\Request::capture();
            $request->setMethod("GET");
            /** @var \App\Http\Middleware\EncryptCookies $encryptCookies */
            $encryptCookies = app()->make(\App\Http\Middleware\EncryptCookies::class);
            /** @var \Illuminate\Http\Response $response */
            $response = $encryptCookies->handle($request, function($request) use ($user) {
                /** @var \Illuminate\Session\Middleware\StartSession $startSession */
                $startSession = app()->make(\Illuminate\Session\Middleware\StartSession::class);
                return $startSession->handle($request, function($request) use ($user) {
                    /** @var \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken $verifyCsrfSession */
                    $verifyCsrfSession = app()->make(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);     
                    return $verifyCsrfSession->handle($request, function($request) use ($user) {
                        /** @var \Illuminate\Http\Request $request */
                        #var_dump( $request->cookies->all(), $request->session()->all(), $request->session()->getId(), $user->id );
                        #die();
                        // Logout
                        \Illuminate\Support\Facades\Auth::login($user);
                        \Illuminate\Support\Facades\Session::save();
                        return new \Illuminate\Http\Response();
                    });
                });
            });
            $response->sendHeaders();
        }
    }
    
    public function loginLaravel($name, $password) {
        require_once $GLOBALS["ab_path"]."/inc.laravel.php";
        $userQuery = \Plugins\Hydromot\User::where("name", "=", $name);
        $success = false;
        if ($userQuery->count() > 0) {
            $user = $userQuery->first();
            
            $request = \Illuminate\Http\Request::capture();
            $request->setMethod("GET");
            /** @var \App\Http\Middleware\EncryptCookies $encryptCookies */
            $encryptCookies = app()->make(\App\Http\Middleware\EncryptCookies::class);
            /** @var \Illuminate\Http\Response $response */
            $response = $encryptCookies->handle($request, function($request) use ($name, $password, &$success) {
                /** @var \Illuminate\Session\Middleware\StartSession $startSession */
                $startSession = app()->make(\Illuminate\Session\Middleware\StartSession::class);
                return $startSession->handle($request, function($request) use ($name, $password, &$success) {
                    /** @var \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken $verifyCsrfSession */
                    $verifyCsrfSession = app()->make(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);     
                    return $verifyCsrfSession->handle($request, function($request) use ($name, $password, &$success) {
                        /** @var \Illuminate\Http\Request $request */
                        #var_dump( $request->cookies->all(), $request->session()->all(), $request->session()->getId(), $user->id );
                        #die();
                        // Logout
                        if (\Illuminate\Support\Facades\Auth::attempt(["name" => $name, "password" => $password])) {
                            \Illuminate\Support\Facades\Session::save();
                            $success = true;
                        }
                        return new \Illuminate\Http\Response();
                    });
                });
            });
            $response->sendHeaders();
        }
        return $success;
    }
    
    public function logout() {
        require_once $GLOBALS["ab_path"]."/inc.laravel.php";
        $request = \Illuminate\Http\Request::capture();
        /** @var \App\Http\Middleware\EncryptCookies $encryptCookies */
        $encryptCookies = app()->make(\App\Http\Middleware\EncryptCookies::class);
        /** @var \Illuminate\Http\Response $response */
        $response = $encryptCookies->handle($request, function($request) {
            /** @var \Illuminate\Session\Middleware\StartSession $startSession */
            $startSession = app()->make(\Illuminate\Session\Middleware\StartSession::class);
            return $startSession->handle($request, function($request) {
                /** @var \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken $verifyCsrfSession */
                $verifyCsrfSession = app()->make(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);     
                return $verifyCsrfSession->handle($request, function($request) {
                    /** @var \Illuminate\Http\Request $request */
                    #var_dump( $request->cookies->all(), $request->session()->all(), $request->session()->getId(), $user->id );
                    #die();
                    // Login
                    \Illuminate\Support\Facades\Auth::logout();
                    \Illuminate\Support\Facades\Session::save();
                    return new \Illuminate\Http\Response();
                });
            });
        });
        $response->sendHeaders();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function queryLeads($params = array()) {
        require_once $GLOBALS["ab_path"]."/inc.laravel.php";
        $query = \Plugins\Hydromot\Lead::where($params);
        return $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function queryLeadsCount($params = array()) {
        return $this->queryLeads($params)->count();
    }

    public function getLead($leadId) {
        require_once $GLOBALS["ab_path"]."/inc.laravel.php";
        return \Plugins\Hydromot\Lead::find($leadId);
    }
    
    /**
     * @param int $limit
     * @param int $page
     * @return array
     */
    public function getLeads($params = array(), $limit = 20, $page = 0) {
        return $this->queryLeads($params)->with(["user.traderUser", "project"])
            ->orderBy("updated_at", "desc")->forPage($page, $limit)->get();
    }

    /**
     * @param int $userId
     * @return \Plugins\Hydromot\User
     */
    public function getUserById($userId) {
        require_once $GLOBALS["ab_path"]."/inc.laravel.php";
        return \Plugins\Hydromot\User::where("trader_id", "=", $userId)->get()->first();
    }
    
    public function getUserByName($userName) {
        $userId = $GLOBALS["db"]->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME LIKE '".mysql_real_escape_string($userName)."'");
        if ($userId > 0) {
            return $this->getUserById($userId);
        } else {
            return null;
        }
    }
    
    public function getUserChatCount() {
        $userId = $GLOBALS["uid"];
        if ($userId > 0) {
            $user = $this->getUserById($userId);
            if ($user !== null) {
                return $user->userChatsUnread()->count();
            }
        }
        return 0;
    }

    /**
     * @return bool|\Plugins\Hydromot\User
     */
    public function getLeadsUser() {
        if ($this->leadsUser === null) {
            require_once $GLOBALS["ab_path"]."/inc.laravel.php";
            
            $this->leadsUser = false;
            $userLead = \Plugins\Hydromot\Facades\Hydromot::user();
            if ($userLead !== null) {
                $this->leadsUser = $userLead;
            }
        }
        return $this->leadsUser;
    }
    
    public function getLeadsUserId() {
        return ($this->getLeadsUser() === false ? 0 : $this->getLeadsUser()->id);
    }
    
    public function getLeadsUserName() {
        return ($this->getLeadsUser() === false ? 0 : addnoparse(stdHtmlentities($this->getLeadsUser()->name)));
    }
    
    public function templateFunction(Api_Entities_EventParamContainer $params) {
        $action = $params->getParam("action");
        /** @var Template $template */
        $template = $params->getParam("template");
        switch ($action) {
            case "article_lead":
                $templateLead = $this->utilGetTemplate("article_lead.htm");
                $templateLead->addvars($template->vars);
                $params->setParam("result", $templateLead->process());
                break;
            case "chats_new":
                $chatCount = $this->getUserChatCount();
                $templateChatsNew = $this->utilGetTemplate("chats_new.htm");
                $templateChatsNew->addvars($template->vars);
                $templateChatsNew->addvar("COUNT", $chatCount);
                $params->setParam("result", $templateChatsNew->process());
                break;
            case "project_count":
                $projectCount = $this->countProjects();
                $params->setParam("result", $projectCount);
                break;
            case "lead_count":
                $leadCount = $this->countLeads();
                $params->setParam("result", $leadCount);
                break;
            case "vendor_profiles":
                list($userId) = $params->getParam("params");
                $arProfiles = $this->dbLeads->fetch_table("
                    SELECT p.* 
                    FROM `profiles` p
                    JOIN `users` u ON p.user_id=u.id
                    LEFT JOIN `user_childs` uc ON uc.user_child_id = u.id
                    LEFT JOIN `users` ucp ON uc.user_parent_id = ucp.id
                    WHERE u.trader_id=".(int)$userId." OR ucp.trader_id=".(int)$userId);
                foreach ($arProfiles as $profileIndex => $profileDetails) {
                    $profileId = $profileDetails["id"];
                    $profilePath = "profile/".($profileId - $profileId % 1000)."/".$profileId."/profile.png";
                    $profileFile = $GLOBALS["ab_path"]."/ebiz-kernel/storage/app/public/".$profilePath;
                    if (file_exists($profileFile)) {
                        $arProfiles[$profileIndex]["avatarUrl"] = $template->tpl_uri_baseurl("/leads/storage/" . $profilePath . "?c=" . filemtime($profileFile));
                    } else {
                        $arProfiles[$profileIndex]["avatarUrl"] = $template->tpl_uri_baseurl("/leads/storage/profile/default/profile.png");
                    }
                }
                $templateProfiles = $this->utilGetTemplate("vendor_profiles.htm");
                $templateProfiles->addvar("liste", $this->utilGetTemplateList("vendor_profiles.row.htm", $arProfiles));
                $params->setParam("result", $templateProfiles->process());
                break;
            case "leadsUserId":
		        $params->setParam("result", $this->getLeadsUserId());
                break;
                
            case "leadsUserName":
		        $params->setParam("result", $this->getLeadsUserName());
                break;
                
	        case "leads":
	            
	            require_once $GLOBALS["ab_path"]."/inc.laravel.php";
	            #dd(\Plugins\Hydromot\Facades\Hydromot::user());
                if ((\Plugins\Hydromot\Facades\Hydromot::user() !== null) && $template->tpl_has_permission("leads,R")) {
                    die(forward( $template->tpl_uri_baseurl("/leads/user/dashboard") ));
                }
	            
		        $tplLeads = $this->utilGetTemplate("leads.htm");
	            $queryLeads = $this->queryLeads([ "moderated" => 1 ]);
	        	$urlParams = $GLOBALS["ar_params"];
	            
	            # Pagination
		        $n_page = 1;
	        	if ( isset($urlParams[1]) && !empty($urlParams[1]) ) {
	        		$n_page = intval($urlParams[1]);
		        }
	        	$perpage = 15;
		        $offset = ($n_page-1)*$perpage;
		        
		        # Search
	        	if ( isset($urlParams[2]) && !empty($urlParams[2]) ) {
	        	    $ref_id = rawurldecode(str_replace("$", "%", $urlParams[2]));
	        	    $queryLeads->where("id", "=", $ref_id);
	        		$tplLeads->addvar("ref_id", $ref_id);
		        }
		        if ( isset($urlParams[3]) && !empty($urlParams[3]) ) {
	        	    $title = rawurldecode(str_replace("$", "%", $urlParams[3]));
	        	    $queryLeads->where("title", "LIKE", "%".$title."%");
			        $tplLeads->addvar("title", $title);
		        }
		        
		        # Result
                /**
                 * Result
                 * @var \Plugins\Hydromot\Lead[] $arLeadsObj
                 * @var array[] $arLeadsAssoc
                 */
	        	$lead_count = $queryLeads->count();
                $arLeadsObj = $queryLeads->limit($perpage)->offset($offset)->get();
                $arLeadsAssoc = array();
                foreach ($arLeadsObj as $lead) {
                    $arLead = $lead->toArray();
                    $arLead["deadline"] = floor((strtotime($lead->due) - time())/60/60/24);
                    $arLead["url"] = ($GLOBALS["uid"] > 0 ? $template->tpl_uri_action("packets_membership_upgrade") : $template->tpl_uri_action("register"));
                    $tplLeadFiles = $this->utilGetTemplate("leads.row.file.htm");
                    $tplLeadFiles->addvars([
                        "all_public" => $lead->documentsPublic()->count(),
                        "all_private" => $lead->documentsPrivate()->count()
                    ]);
                    $arLead["documents"] = $tplLeadFiles->process();
                    $arLeadsAssoc[] = $arLead;
	        	}
		        $tplLeads->addvar('list_leads', $this->utilGetTemplateList("leads.row.htm", $arLeadsAssoc));
		        $tplLeads->addvar('leads_count', $lead_count);
		        $tplLeads->addvar('pager', htm_browse_extended($lead_count, $n_page, "leads-directory,{PAGE},".$urlParams[2].",".$urlParams[3], $perpage) );
	            $tplLeads->addvar('n_page', $n_page);
		        $params->setParam("result", $tplLeads->process());
		        break;
        }
    }
    
    private function userPasswordHash($passwordPlain) {
        $hash = password_hash($passwordPlain, PASSWORD_BCRYPT, [
            'cost' => 10,
        ]);
        return $hash;
    }
    
    public function userRegisterCheck(Api_Entities_EventParamContainer $params) {
        $data = $params->getParam("data");
        $errors = $params->getParam("errors");
        $changed = false;
        if (!in_array("NAME_EXISTS", $errors)) {
            // Check for duplicate names
            $checkName = $this->dbLeads->fetch_atom("SELECT COUNT(*) FROM `users` WHERE name LIKE '".mysql_real_escape_string($data["NAME"])."'");
            if ($checkName > 0) {
                $errors[] = "NAME_EXISTS";
                $changed = true;
            }
        }
        if (!in_array("EMAIL_EXISTS", $errors)) {
            // Check for duplicate names
            $checkEmail = $this->dbLeads->fetch_atom("SELECT COUNT(*) FROM `users` WHERE email LIKE '".mysql_real_escape_string($data["EMAIL"])."'");
            if ($checkEmail > 0) {
                $errors[] = "EMAIL_EXISTS";
                $changed = true;
            }
        }
        if ($changed) {
            $params->setParam("errors", $errors);
        }
    }
    
    public function userNew($arUser) {
        $userId = $arUser["id"];
        $userData = $arUser["data"];
        $queryResult = $this->dbLeads->querynow("
            INSERT INTO `users`
              (`name`, `email`, `password`, `remember_token`, `created_at`, `updated_at`, `trader_id`)
            VALUES
              ('".mysql_real_escape_string($userData["NAME"])."', '".mysql_real_escape_string($userData["EMAIL"])."',
              '".mysql_real_escape_string($this->userPasswordHash($userData["pass1"]))."', null, NOW(), NOW(), ".(int)$userId.");");
        if ($queryResult["int_result"] > 0) {
            // Add roles:
            //  1 = Projekte / Projekte erstellen, bearbeiten und zugehörige Leads verwalten
            //  2 = Leads / Durchsuchen und Kaufen von Leads
            //  3 = Accounts verwalten / Erlaubt es Mitarbeiter einzuladen und zu bearbeiten
            $this->dbLeads->querynow("
                INSERT INTO `access_role_user`
                  (`user_id`, `access_role_id`)
                VALUES
                  (".$queryResult["int_result"].", 1),
                  (".$queryResult["int_result"].", 2),
                  (".$queryResult["int_result"].", 3);");
            $this->dbLeads->querynow("
                INSERT INTO `profiles`
                  (`user_id`, `first_name`, `last_name`, `email`, `created_at`, `updated_at`)
                VALUES
                  (".$queryResult["int_result"].", '".mysql_real_escape_string($userData["VORNAME"])."', '".mysql_real_escape_string($userData["NACHNAME"])."',
                    '".mysql_real_escape_string($userData["EMAIL"])."', NOW(), NOW());");
        }
    }
    
    public function userDelete(Api_Entities_EventParamContainer $params) {
        $userId = $params->getParam("ID_USER");
        $userName = $params->getParam("NAME");
        $this->dbLeads->querynow("
            DELETE FROM `users`
            WHERE `trader_id`=".(int)$userId);
    }
    
    public function userProfileCheck(Api_Entities_EventParamContainer $params) {
        $userId = $params->getParam("id");
        $isAdmin = $params->getParam("admin");
        $userData = $params->getParam("data");
        $emailDuplicateTrader = $this->db->fetch_atom("
            SELECT COUNT(*) FROM `user` 
            WHERE ID_USER!=".(int)$userId." AND EMAIL='".mysql_real_escape_string($userData["EMAIL"])."'");
        $emailDuplicateLeads = $this->dbLeads->fetch_atom("
            SELECT COUNT(*) FROM `users` 
            WHERE `trader_id`!=".(int)$userId." AND `email`='".mysql_real_escape_string($userData["EMAIL"])."'");
        #die(var_dump($emailDuplicateLeads, $emailDuplicateTrader));
        if (($emailDuplicateTrader > 0) || ($emailDuplicateLeads > 0)) {
            if ($isAdmin) {
                $params->setParamArrayAppend("errors", "Diese E-Mail Adresse ist bereits in Verwendung!");
            } else {
                $params->setParamArrayAppend("errors", "EMAIL_EXISTS");
            }
        }
    }
    
    public function userProfileChange($arUser) {
        $userId = $arUser["id"];
        $userData = $arUser["data"];
        $userLeadId = $this->dbLeads->fetch_atom("SELECT `id` FROM `users` WHERE `trader_id`=".(int)$userId);
        
        if ($userLeadId > 0) {
            $this->dbLeads->querynow("
                UPDATE `profiles`
                SET `first_name`='".mysql_real_escape_string($userData["VORNAME"])."',
                    `last_name`='".mysql_real_escape_string($userData["NACHNAME"])."'
                WHERE `user_id`=".(int)$userLeadId);
            if (!empty($userData["pass1"])) {
                $this->dbLeads->querynow("
                    UPDATE `users`
                    SET `password`='".mysql_real_escape_string($this->userPasswordHash($userData["pass1"]))."',
                        `email`='".mysql_real_escape_string($userData["EMAIL"])."'
                    WHERE `id`=".(int)$userLeadId);
            } else {
                $this->dbLeads->querynow("
                    UPDATE `users`
                    SET `email`='".mysql_real_escape_string($userData["EMAIL"])."'
                    WHERE `id`=".(int)$userLeadId);
            }
        }
    }
    
    public function userGetId($traderId) {
        return $this->dbLeads->fetch_atom("SELECT `id` FROM `users` WHERE `trader_id`=".(int)$traderId);
    }
    
    public function leadCreate($articleId, $valueMin = null, $valueMax = null, $dateDue = null, $quantity = 1, $exclusive = true) {
        $userIdTrader = $GLOBALS["uid"];
        $userIdLeads = $this->userGetId($userIdTrader);
        $article = Api_Entities_MarketplaceArticle::getById($articleId);
        // Get values
        $description = $article->getDescriptionText();
        if ($valueMin === null) {
            $valueMin = $article->getPrice();
        }
        if ($valueMax === null) {
            $valueMax = $article->getPrice();
        }
        if ($dateDue === null) {
            $dateDue = date("Y-m-d H:i:s", strtotime("+2 weeks"));
        }
        $dateCreated = $dateUpdated = date("Y-m-d H:i:s");
        $status = ($exclusive ? "exclusive" : "public");
        // Insert lead
        $leadInsert = $this->dbLeads->querynow($q="
            INSERT INTO `leads`
              (`status`, `user_id`, `description`, `valid_until`, `due`, `valueMin`, `valueMax`, `created_at`, `updated_at`)
            VALUES
              ('".mysql_real_escape_string($status)."', ".(int)$userIdLeads.", '".mysql_real_escape_string($description)."',
              '".mysql_real_escape_string($dateDue)."', '".mysql_real_escape_string($dateDue)."',
              '".mysql_real_escape_string($valueMin)."', '".mysql_real_escape_string($valueMax)."',
              '".mysql_real_escape_string($dateCreated)."', '".mysql_real_escape_string($dateUpdated)."');");
        $leadId = $leadInsert["int_result"];
        // Link article
        $this->dbLeads->querynow("
            INSERT INTO `leads_articles`
              (`article_trader_id`, `lead_id`, `title`, `quantity`)
            VALUES
              (".(int)$articleId.", ".(int)$leadId.", '".mysql_real_escape_string($article->getTitle())."', ".(int)$quantity.");");
        return true;
    }
}