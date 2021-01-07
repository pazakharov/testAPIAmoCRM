<?php

use AmoCRM\Models\TaskModel;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Collections\ContactsCollection;
use League\OAuth2\Client\Token\AccessTokenInterface;

include_once __DIR__ . '/bootstrap.php';

$accessToken = getToken();

$apiClient->setAccessToken($accessToken)
	->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
	->onAccessTokenRefresh(
		function (AccessTokenInterface $accessToken, string $baseDomain) {
			saveToken(
				[
					'accessToken' => $accessToken->getToken(),
					'refreshToken' => $accessToken->getRefreshToken(),
					'expires' => $accessToken->getExpires(),
					'baseDomain' => $baseDomain,
				]
			);
		}
	);

$contactsService = $apiClient->contacts();
$contactsFilter = new ContactsFilter();
//$contactsFilter->setLimit(API_PER_PAGE_LIMIT);
$contactsFilter->setLimit(10);

$tasksService = $apiClient->tasks();
$tasksCollection = new TasksCollection();
$contactsCollection = new ContactsCollection();

function fetchAllEntities($collection, $service, $filter, $next = FALSE, $with = ['leads'])
{
	sleep(1);
	$fetchedCollection = new ContactsCollection();
	if ($next == TRUE) {
		try {
			print_r($collection->count());
			$fetchedCollection = $service->nextPage($collection);
			
		} catch (AmoCRMApiException $e) {
			printError($e);
			//die;
		}
	} else {
		try {
			$fetchedCollection = $service->get($filter, $with);
		} catch (AmoCRMApiException $e) {
			printError($e);
			//die;
		}
	}

	foreach ($fetchedCollection as $entity) {
		$collection->add($entity);
	}
	//print_r($fetchedCollection->getNextPageLink());
	if ($fetchedCollection->getNextPageLink()) {
		fetchAllEntities($fetchedCollection, $service, $filter,TRUE);
	}
	print_r($collection->count());

	return $collection;
}

$contactsCollection = fetchAllEntities($contactsCollection, $contactsService, $contactsFilter);
echo ($contactsCollection->count());
die;


foreach ($contactsCollection as $contactObj) {
	if (!$contactObj->leads) {
		$task = new TaskModel();
		$task->setText('Контакт без сделок')
			->setCompleteTill(mktime(23, 59, 59, date("n"), date("j"), date("Y")))
			->setEntityType(EntityTypesInterface::CONTACTS)
			->setEntityId($contactObj->id);
		$tasksCollection->add($task);
	}
}

try {
	$tasksCollection = $tasksService->add($tasksCollection);
} catch (AmoCRMApiException $e) {
	printError($e);
	die;
}
echo 'Количество созданных задач: ' . $tasksCollection->count();
