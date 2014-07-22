<?php
/**
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class AdminCronJobsController extends ModuleAdminController
{
	public function __construct()
	{
		if (Tools::getValue('token') != Configuration::get('CRONJOBS_EXECUTION_TOKEN'))
			die('Invalid token');

		parent::__construct();

		$this->postProcess();

		die;
	}

	public function postProcess()
	{
		$this->module->sendCallback();

		ob_start();

		$this->runModulesCrons();
		$this->runTasksCrons();

		ob_end_clean();
	}

	protected function runModulesCrons()
	{
		$query = 'SELECT * FROM '._DB_PREFIX_.$this->module->name.' WHERE `active` = 1 AND `id_module` IS NOT NULL';
		$crons = Db::getInstance()->executeS($query);

		if (is_array($crons) && (count($crons) > 0))
			foreach ($crons as &$cron)
				if ($this->shouldBeExecuted($cron) == true)
				{
					Hook::exec('actionCronJob', array(), $cron['id_module']);
					Db::getInstance()->execute('UPDATE '._DB_PREFIX_.$this->module->name.' SET `updated_at` = NOW() WHERE `id_cronjob` = \''.$cron['id_cronjob'].'\'');
				}
	}

	protected function runTasksCrons()
	{
		$query = 'SELECT * FROM '._DB_PREFIX_.$this->module->name.' WHERE `active` = 1 AND `id_module` IS NULL';
		$crons = Db::getInstance()->executeS($query);

		if (is_array($crons) && (count($crons) > 0))
			foreach ($crons as &$cron)
				if ($this->shouldBeExecuted($cron) == true)
				{
					Tools::file_get_contents(urldecode($cron['task']), false);
					Db::getInstance()->execute('UPDATE '._DB_PREFIX_.$this->module->name.' SET `updated_at` = NOW() WHERE `id_cronjob` = \''.$cron['id_cronjob'].'\'');
				}
	}

	protected function shouldBeExecuted($cron)
	{
		$hour = ($cron['hour'] == -1) ? date('H') : $cron['hour'];
		$day = ($cron['day'] == -1) ? date('d') : $cron['day'];
		$month = ($cron['month'] == -1) ? date('m') : $cron['month'];
		$day_of_week = ($cron['day_of_week'] == -1) ? date('D') : date('D', strtotime('Sunday +'.($cron['day_of_week'] - 1).' days'));

		$execution = $day_of_week.' '.date('Y').'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT).' '.str_pad($hour, 2, '0', STR_PAD_LEFT);
		$now = date('D Y-m-d H');

		return !(bool)strcmp($now, $execution);
	}

}
