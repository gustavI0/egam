<?php

namespace Drupal\egam_global;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\egam_global\Form\ListFilterForm;

class CustomEntityListBuilder extends EntityListBuilder {

	/**
	 * {@inheritdoc}
	 */
	public function buildHeader(): array {
		$header['label'] = [
			'data' => $this->t('Label'),
			'specifier' => 'label',
			'field' => 'label',
		];
		$header['status'] = [
			'data' => $this->t('Status'),
			'specifier' => 'status',
			'field' => 'status',
			'class' => [RESPONSIVE_PRIORITY_LOW],
		];
		$header['uid'] = [
			'data' => $this->t('Author'),
			'specifier' => 'uid',
			'field' => 'uid',
			'class' => [RESPONSIVE_PRIORITY_LOW],
		];
		$header['created'] = [
			'data' => $this->t('Created'),
			'specifier' => 'created',
			'field' => 'created',
			'class' => [RESPONSIVE_PRIORITY_LOW],
		];
		$header['changed'] = [
			'data' => $this->t('Updated'),
			'specifier' => 'changed',
			'field' => 'changed',
			'sort' => 'desc',
			'class' => [RESPONSIVE_PRIORITY_LOW],
		];
		return $header + parent::buildHeader();
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildRow(EntityInterface $entity): array {
		$row['label'] = $entity->toLink();
		$row['status'] = $entity->get('status')->value ? $this->t('Enabled') : $this->t('Disabled');
		$username_options = [
			'label' => 'hidden',
			'settings' => ['link' => $entity->get('uid')->entity->isAuthenticated()],
		];
		$row['uid']['data'] = $entity->get('uid')->view($username_options);
		$row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
		$row['changed']['data'] = $entity->get('changed')->view(['label' => 'hidden']);

		return $row + parent::buildRow($entity);
	}

	/**
	 * {@inheritdoc}
	 */
	public function load(): array {
		$header = $this->buildHeader();
		$query = $this->getStorage()->getQuery()->accessCheck();
		// Récupération des paramètres de filtre
		$filterLabel = \Drupal::request()->query->get('filter_label');
		// Application du filtre de titre si présent
		if (!empty($filterLabel)) {
			$query->condition('label', '%' . $filterLabel . '%', 'LIKE');
		}

		// Extension de tri
		$query->tableSort($header);

		// Instanciation du pager
		$query->pager(50);

		// Exécution de la requête
		$ids = $query->execute();
		return $this->getStorage()->loadMultiple($ids);
	}

	/**
	 * {@inheritdoc}
	 */
	public function render(): array {
		// Utilisation de la méthode de tri de TableSort
		$build['filter_form'] = \Drupal::formBuilder()->getForm(ListFilterForm::class);
		$build['table'] = parent::render();
		return $build;
	}

}