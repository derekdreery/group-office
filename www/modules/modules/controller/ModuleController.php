<?php

namespace GO\Modules\Controller;

use GO;
use GO\Base\Model\Module;
use GO\Base\Controller\AbstractJsonController;
use GO\Base\Model\Acl;

use GO\Base\Data\DbStore;
use GO\Base\Data\ColumnModel;
use GO\Base\Db\FindParams;
use GO\Base\Data\JsonResponse;

class ModuleController extends AbstractJsonController{
	
	protected function allowWithoutModuleAccess() {
		return array('permissionsstore');
	}
	
	protected function ignoreAclPermissions() {		
		return array('*');
	}
	
	
	protected function actionUpdate($id) {

		$model = Module::model()->findByPk($id);
		$model->setAttributes($_POST);		
		$model->save();
		
		echo $this->renderSubmit($model);
	}
	
	
	/**
	 * Render JSON output that can be used by ExtJS GridPanel
	 * @param array $params the $_REQUEST params
	 */
	protected function actionStore() {
		//Create ColumnModel from model
		$columnModel = new ColumnModel(Module::model());
		
		$columnModel->formatColumn('description', '$model->moduleManager->description()');
		$columnModel->formatColumn('name', '$model->moduleManager->name()');
		$columnModel->formatColumn('author', '$model->moduleManager->author()');
		$columnModel->formatColumn('icon', '$model->moduleManager->icon()');
		$columnModel->formatColumn('appCentre', '$model->moduleManager->appCentre()');
		$columnModel->formatColumn('warning', '$model->getWarning()');
		
		$findParams = FindParams::newInstance()
						->ignoreAcl()
						->limit(0);
		
		if(!empty(GO::config()->allowed_modules)){
			$findParams->getCriteria ()->addInCondition ('id', explode(',',GO::config()->allowed_modules));
		}
		
		//Create store
		$store = new DbStore('GO\Base\Model\Module', $columnModel, $_POST, $findParams);
		$store->defaultSort='sort_order';
		$response = $this->renderStore($store);		
		echo $response;
	}
	
	
	protected function actionAvailableModulesStore($params){
		
		$response=new JsonResponse(array('results','success'=>true));
		
		$modules = GO::modules()->getAvailableModules();
		
		$availableModules=array();
						
		foreach($modules as $moduleClass){		
			
			$module = new $moduleClass;//call_user_func($moduleClase();			
			$availableModules[$module->name()] = array(
					'id'=>$module->id(),
					'name'=>$module->name(),
					'description'=>$module->description(),
					'icon'=>$module->icon()
			);
		}
		
		ksort($availableModules);		
		
		$response['results']=array_values($availableModules);
		
		$response['total']=count($response['results']);
		
		echo $response;
	}
	
	
	protected function actionInstall($params){
		
		$response =new JsonResponse(array('success'=>true,'results'=>array()));
		$modules = json_decode($params['modules'], true);
		foreach($modules as $moduleId)
		{
			if(!GO::modules()->$moduleId){
				$module = new Module();
				$module->id=$moduleId;


				$module->moduleManager->checkDependenciesForInstallation($modules);	

				if(!$module->save())
					throw new \GO\Base\Exception\Save();

				$response->data['results'][]=array_merge($module->getAttributes(), array('name'=>$module->moduleManager->name()));
			}
		}
		
//		$defaultModels = \GO\Base\Model\AbstractUserDefaultModel::getAllUserDefaultModels();
//		
//		$stmt = \GO\Base\Model\User::model()->find(\GO\Base\Db\FindParams::newInstance()->ignoreAcl());		
//		while($user = $stmt->fetch()){
//			foreach($defaultModels as $model){
//				$model->getDefault($user);
//			}
//		}
				
		echo $response;
	}
	
	public function actionPermissionsStore($params) {
		
		
		//check access to users or groups module. Because we allow this action without
		//access to the modules module		
		if ($params['paramIdType']=='groupId'){
			if(!GO::modules()->groups)
				throw new \GO\Base\Exception\AccessDenied();
		}else{
			if(!GO::modules()->users)
				throw new \GO\Base\Exception\AccessDenied();
		}
			
		$response = new JsonResponse(array(
			'success' => true,
			'results' => array(),
			'total' => 0
		));
		$modules = array();
		$mods = GO::modules()->getAllModules();
			
		while ($module=array_shift($mods)) {
			$permissionLevel = 0;
			$usersGroupPermissionLevel = false;
			if (empty($params['id'])) {				
				$aclUsersGroup = $module->acl->hasGroup(GO::config()->group_everyone); // everybody group
				$permissionLevel=$usersGroupPermissionLevel=$aclUsersGroup ? $aclUsersGroup->level : 0;
			} else {
				if ($params['paramIdType']=='groupId') {
					//when looking at permissions from the groups module.
					$aclUsersGroup = $module->acl->hasGroup($params['id']);
					$permissionLevel=$aclUsersGroup ? $aclUsersGroup->level : 0;
				} else {
					//when looking from the users module
					$permissionLevel = Acl::getUserPermissionLevel($module->acl_id, $params['id']);					
					$usersGroupPermissionLevel= Acl::getUserPermissionLevel($module->acl_id, $params['id'], true);
				}
			}
			
			$translated = $module->moduleManager ? $module->moduleManager->name() : $module->id;
			
			// Module permissions only support read permission and manage permission:
			if (Acl::hasPermission($permissionLevel,Acl::CREATE_PERMISSION))
				$permissionLevel = Acl::MANAGE_PERMISSION;			
			
			$modules[$translated]= array(
				'id' => $module->id,
				'name' => $translated,
				'permissionLevel' => $permissionLevel,
				'disable_none' => $usersGroupPermissionLevel!==false && Acl::hasPermission($usersGroupPermissionLevel,Acl::READ_PERMISSION),
				'disable_use' => $usersGroupPermissionLevel!==false && Acl::hasPermission($usersGroupPermissionLevel, Acl::CREATE_PERMISSION)
			);
			$response['total'] += 1;
		}
		ksort($modules);

		$response['results'] = array_values($modules);
		
		return $response;
	}
	
	
	/**
	 * Checks default models for this module for each user.
	 * 
	 * @param array $params 
	 */
	public function actionCheckDefaultModels($params) {
		
		GO::session()->closeWriting();
		
//		GO::$disableModelCache=true;
		$response = new JsonResponse(array('success' => true));
		$module = Module::model()->findByPk($params['moduleId']);
		
		
		$models = array();
		$modMan = $module->moduleManager;
		if ($modMan) {
			$classes = $modMan->findClasses('model');
			foreach ($classes as $class) {
				if ($class->isSubclassOf('GO\Base\Model\AbstractUserDefaultModel')) {
					$models[] = GO::getModel($class->getName());
				}
			}
		}
		
		$module->acl->getAuthorizedUsers(
						$module->acl_id, 
						Acl::READ_PERMISSION, 
						function($user, $models){		
							foreach ($models as $model)
								$model->getDefault($user);		
						}, array($models));
		


		echo $response;
	}
	
	public function actionSaveSortOrder($params){
		$modules = json_decode($params['modules']);
		
		$i=0;
		foreach($modules as $module){
			$moduleModel = Module::model()->findByPk($module->id);
			$moduleModel->sort_order=$i++;
			$moduleModel->save();
		}
		
		echo new JsonResponse(array('success'=>true));
	}

}

