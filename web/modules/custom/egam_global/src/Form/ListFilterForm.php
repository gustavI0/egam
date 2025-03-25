<?php

namespace Drupal\egam_global\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ListFilterForm extends FormBase {

	private ?Request $request;

	private mixed $route;

	public function __construct(RequestStack $requestStack) {
		$this->request = $requestStack->getCurrentRequest();
		$this->route = $this->request->attributes->get('_route');
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container): ListFilterForm|static {
		return new static(
			$container->get('request_stack')
		);
	}

	public function getFormId(): string {
		return 'list_filter_form';
	}

	public function buildForm(array $form, FormStateInterface $form_state) {
		// Récupération du filtre actuel
		$filterLabel = \Drupal::request()->query->get('filter_label');

		$form['filter_label'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Filter by label'),
			'#default_value' => $filterLabel,
			'#size' => 30,
			'#placeholder' => $this->t('Enter label to filter'),
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Filter'),
		];

		$form['actions']['reset'] = [
			'#type' => 'submit',
			'#value' => $this->t('Reset'),
			'#submit' => ['::resetForm'],
		];

		return $form;
	}

	public function submitForm(array &$form, FormStateInterface $form_state): void {
		// Redirection avec le paramètre de filtre
		$query = $this->request->query->all();
		$query['filter_label'] = $form_state->getValue('filter_label');

		$form_state->setRedirect(
			$this->route,
			[],
			['query' => $query]
		);
	}

	/**
	 * Méthode de réinitialisation du formulaire
	 */
	public function resetForm(array &$form, FormStateInterface $form_state) {
		$form_state->setRedirect($this->route);
	}

}