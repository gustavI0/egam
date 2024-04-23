<?php

namespace Drupal\egam_global;

trait SingletonTrait {

	public static function me(): self {
		return \Drupal::service(static::class);
	}

}