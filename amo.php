<?php

use AmoCRM\Models\TaskModel;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Exceptions\AmoCRMApiException;
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
$contactsFilter->setLimit(API_PER_PAGE_LIMIT);

$tasksService = $apiClient->tasks();
$tasksCollection = new TasksCollection();

try {
	$contactsCollection = $contactsService->get($contactsFilter, ['leads']);
} catch (AmoCRMApiException $e) {
	printError($e);
	die;
}

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
