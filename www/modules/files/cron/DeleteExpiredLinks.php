<?php

namespace GO\Files\Cron;
use GO;
use GO\Base\Cron\AbstractCron;

use GO\Files\Model\File;
use GO\Base\Db\FindParams;
use GO\Base\Db\FindCriteria;


class DeleteExpiredLinks extends AbstractCron {

    /**
     * Return true or false to enable the selection for users and groups for
     * this cronjob.
     *
     * @return bool
     */
    public function enableUserAndGroupSupport()
    {
        return false;
    }

    /**
     * Get the unique name of the Cronjob
     *
     * @return string
     */
    public function getLabel()
    {
        return GO::t("deleteExpiredLabel", 'files');
    }

    /**
     * Get the unique name of the Cronjob
     *
     * @return string
     */
    public function getDescription()
    {
        return GO::t("deleteExpiredDescription", 'files');
    }

    /**
     * The code that needs to be called when the cron is running
     *
     * @param GO_Base_Cron_CronJob $cronJob
     * @param GO_Base_Model_User $user
     */
    public function run(GO\Base\Cron\CronJob $cronJob, GO\Base\Model\User $user = null)
    {
			
			$filesStmt = File::model()->find(
				FindParams::newInstance()
					->ignoreAcl()
					->criteria(FindCriteria::newInstance()
						->addCondition('expire_time',time(),'<')
						->addCondition('expire_time','0','>')
						->addCondition('random_code','','!=')
						->addCondition('delete_when_expired','1')
					)
			);
			
			foreach ($filesStmt as $fileModel)
				$fileModel->delete();
			
    }
}
