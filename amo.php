<?php

use AmoCRM\Models\TaskModel;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\BaseApiCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Collections\ContactsCollection;
use League\OAuth2\Client\Token\AccessTokenInterface;

include_once __DIR__ . '/bootstrap.php';

/**
 * Функция возвращает коллекцию всех сущностей
 * @param mixed $collection
 * @param mixed $service
 * @param mixed $filter
 * @param  $next 
 * @param array $with
 * 
 * @return [type]
 */
function fetchAllEntities($collection, $service, $filter, $with, $next = FALSE)
{
	if ($next == TRUE) {
		try {
			$fetchedCollection = $service->nextPage($collection);
		} catch (AmoCRMApiException $e) {
			//printError($e);
			//die;
		}
	} else {
		try {
			$fetchedCollection = $service->get($filter, $with);
		} catch (AmoCRMApiException $e) {
			printError($e);
			die;
		}
	}

	if (isset($fetchedCollection) && ($fetchedCollection instanceof BaseApiCollection)) {
		foreach ($fetchedCollection as $entity) {
			$collection->add($entity);
		}
		if (method_exists($fetchedCollection, 'getNextPageLink') && $fetchedCollection->getNextPageLink()) {
			$collection->setNextPageLink($fetchedCollection->getNextPageLink());
			$collection = fetchAllEntities($collection, $service, $filter, $with, TRUE);
		}
	}

	return $collection;
}

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
$contactsFilter->setLimit(API_PER_PAGE_LIMIT);

$tasksService = $apiClient->tasks();
$tasksCollection = new TasksCollection();
$contactsCollection = new ContactsCollection();

$contactsCollection = fetchAllEntities($contactsCollection, $contactsService, $contactsFilter, ['leads']);

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
