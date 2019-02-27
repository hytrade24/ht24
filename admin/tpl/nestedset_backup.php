<?php
/* ###VERSIONSBLOCKINLCUDE### */


#die("test");
#$SILENCE=false;
require_once './sys/lib.nestedset_backup.php';

class nestedset_backup_frontend extends nestedset_backup {
	var $opt;
	var $db;
	var $tpl_content;

	function run ($opt='', &$tpl_content) {
		$this->opt = $opt;
		$this->tpl_content = &$tpl_content;

		if ($opt == 'make_backup') {
			$this->opt_make_backup();
		} elseif($opt == 'delete_dump') {
			$this->opt_delete_dump();
		} elseif($opt == 'search_dump') {
			$this->opt_search();
		} elseif($opt == 'restore_backup') {
			$this->opt_restore();
		} elseif($opt == 'optimize') {
			$this->tidy_up();
		}
		else {
			$this->entry();
		}
	}

	function opt_make_backup () {
		if($this->make_backup($_POST['backupname'], 0)) {
				$this->tpl_content->addvar('make_backup_ok', 'Backup wurde erstellt.');
		} else {
			$this->tpl_content->addvar('make_backup_err', 'Backup konnte nicht erstellt werden!');
		}
		$this->tidy_up();
		$this->entry();
	}

	function opt_delete_dump () {
		if (!empty($_POST['backup_ts'])) {
			if ($this->delete_dump($_POST['backup_ts'])) {
				$this->tpl_content->addvar('delete_ok', 'Backup wurde gel&ouml;scht.');
			} else {
				$this->tpl_content->addvar('delete_err', 'Backup konnte nicht gel&ouml;scht werden!');
			}
		}
		$this->entry();
	}

	function opt_search () {
		$this->tpl_content->addlist('list', $this->search_dump($_POST['backupname']), 'tpl/de/nestedset_backup.row.htm') ;
	}

	function opt_restore () {
		if ($this->make_backup()) {
			if ($this->restore_backup($_POST['backup_ts'])) {
				$this->tpl_content->addvar('restore_ok', 'Daten wurden wiederhergestellt.');
			} else {
				$this->tpl_content->addvar('restore_err', 'Daten konnten nicht wiederhergestellt werden!');
			}
		} else {
			$this->tpl_content->addvar('restore_err', 'Daten konnten nicht wiederhergestellt werden!');
		}
		$this->entry();
	}

	function entry() {
		$this->tpl_content->addlist('list', $this->get_dumps(0, 60), 'tpl/de/nestedset_backup.row.htm') ;
	}
}

$nestedset_backup_frontend = new nestedset_backup_frontend($db);
$nestedset_backup_frontend->run((!empty($_POST['opt']) ? $_POST['opt'] : ''), $tpl_content);

?>