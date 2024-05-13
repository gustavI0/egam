<?php

namespace Drupal\egam_global\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\egam_global\SingletonTrait;

class ContextManager {

	use SingletonTrait;

	public function __construct(protected readonly RouteMatchInterface $routeMatch) {
	}

	public function getContext(): ?ContentEntityInterface {
		$routeParameters = $this->routeMatch->getParameters()->all();
		if (empty($routeParameters) || isset($routeParameters['view_id'])) {
			return NULL;
		}
		return reset($routeParameters);
	}

}