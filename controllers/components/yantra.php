<?php

/**
 * A Component aimed at implementing the fundamentals
 * of a simple finite state machine.
 *
 * State tracking is done through sessions; perhaps one
 * day I'll implement a more generic state tracking mechanism
 * that could use a DB backend.
 *
 * @package wowwee
 * @subpackage wowwee.controllers.components
 *
 */
class YantraComponent extends Object {

	/**
	 * Include components used by this component
	 *
	 * @var array
	 * @access public
	 */
	public $components = array('Session');

	/**
	 * Storage namespace identifier.
	 *
	 * @var string
	 * @access public
	 */
	public $namespace = 'StateMachine';

	/**
	 * The default state. If this is not set, the
	 * first entry in $states is used as the default.
	 *
	 * @var array
	 * @access public
	 */
	public $default = null;

	/**
	 * An array of the states (actions) that are under control of
	 * the state machine. If the array is associative, the
	 * key is the state alias, and the value corresponds to a
	 * controller action that represents the state. If the controller
	 * action does not exist, an error is produced.
	 *
	 * @var array
	 * @access public
	 */
	public $states = array();

	/**
	 * An array of the possible transitions between different $states
	 * the state machine.
	 *
	 * @var array
	 * @access public
	 */
	public $transitions = array();

	/**
	 * Determines if the redirect to the new state should be performed
	 * automatically on successful event processing. Defaults to false.
	 *
	 * @var boolean True if auto-redirect should occur, false otherwise.
	 * @access public;
	 */
	public $auto = false;

	/**
	 * Hold a reference to the controller which instantiated this component.
	 *
	 * @var object Controller object
	 * @access public;
	 */
	public $controller = null;

	/**
	 * Component initialize method.
	 * Is called before the controller beforeFilter method. All local
	 * component initialization is done here.
	 *
	 * @param  object $controller A reference to the controller which
	 *                            initialized this component
	 * @param  array  $settings   Optional component configurations
	 * @return void
	 * @access public
	 */
	public function initialize(&$controller, $settings = array()) {
		$this->controller = $controller;

		foreach ($settings as $setting => $value) {
			$this->{$setting} = $value;
		}
	}

	/**
	 * Component startup method.
	 * Is called after the controller's beforeFilter method,
	 * but before the controller action is run.
	 *
	 * @param  object $controller A reference to the controller which
	 *                            initialized this component
	 * @return void
	 * @access public
	 */
	public function startup(&$controller) {
		$this->events = $this->events();

	   if (empty($this->states)) {
			   return trigger_error("You must specify at least one state for the Yantra state machine", E_USER_WARNING);
	   }

		if (!isset($this->default)) {
			$states = array_keys($this->states());
			$this->default = $states[0];
		}

		if (!$this->Session->check("{$this->namespace}.current_state")) {
			$this->state = $this->default;
		}

		$currentAction = $this->controller->action;
		$currentState = $this->state();

		if (isset($currentAction)) {
			  if (!$this->transition($currentState, $this->_actionToState($currentAction))) {
				$this->Session->setFlash(__('You cannot access that page at this point in the process', true), 'flash/error', 'Yantra');

				$states  = $this->states();
				$url = $this->_toUrl($states[$currentState]);
				$this->controller->redirect($url);
			}

		}
	}


	/**
	 * Transitions from one state to the next
	 * possible state in the allowed transition map.
	 *
	 * @param  string  $from The start state
	 * @param  string  $to   The end state
	 * @return boolean       True on successful transition, false otherwise
	 * @access public
	 * @todo Perhaps move the logic for checking the current state to somewhere more appropriate.
	 */
	public function transition($from, $to) {
		if ($from === $to) return true;

		$transitions = $this->transitions();

		if (isset($transitions[$from]) && (in_array($to, $transitions[$from]))) {
			if ($this->state()  === $from) {
				$this->Session->write("{$this->namespace}.destination_state", $to);
				return $this->state($to);
			}
		}

		return false;
	}

	public function transitions() {
		$transitions = array();

		foreach ($this->transitions as $event => &$trans) {
			foreach ($trans as $origin => &$destination) {
				$transitions[$origin][] = $destination;
			}
		}

		return $transitions;
	}

	/**
	 * Trigger the event & corresponding transition
	 *
	 * @param string $event The event that is ocurring
	 * @return mixed        If YantraComponent::$auto is true, a redirect is performed on
	 *                      successful event transition. If $auto is false, a boolean
	 *                      is returned indicating the success/failure of the event transition.
	 * @access public
	 */
	public function event($event) {
		$state = $this->state();

		if (!isset($this->transitions[$event][$state])) return false;

		$to = $this->transitions[$event][$state];
		$transition = $this->transition($state, $to);

		if ($this->auto) {
			$url = $this->_toUrl($this->states[$to]);
			$this->controller->redirect($url);
		}

		return $transition;
	}

	public function events() {
		return array_keys($this->transitions);
	}

	/**
	 * Returns the current state from the object responsible for
	 * storage.
	 *
	 * @param  string $state If Set, the given $state will be saved.
	 * @return string        An identifier representing the current state if $state is null,
	 *                       or a boolean indicating the success or failure of saving
	 *                       the state to the session otherwise.
	 * @access public
	 */
	public function state($state = null) {
		$path = "{$this->namespace}.current_state";

		if ($state) {
			return $this->Session->write($path, $state);
		}

		if($this->Session->check($path)) {
			return $this->Session->read($path);
		}

		return $this->default;
	}

	/**
	 * Returns all possible states
	 *
	 * @return array Array of all possible states
	 * @access public
	 */
	public function states() {
		return $this->states;
	}

	/**
	 * A helpful recursively defined method to determine the state that
	 * a corresponding action is contained in.
	 *
	 * @param string $action
	 * @param array  $states
	 * @return mixed
	 * @access public
	 */
	protected function _actionToState($action, $states = array()) {
		if (empty($states)) {
			$states= $this->states();
		}

		foreach($states as $key=>$value) {
			$current_key = $key;
			if ($action === $value || (is_array($value) && $this->_actionToState($action, $value) !== null)) {
				return $current_key;
			}
		}

		return null;
	}

	/**
	 * Converts a state to a controller action
	 *
	 * NOTE: a state may represent many controller actions, in which case
	 * one of them will have to be the default. This function arbitrarily
	 * chooses the first controller action as the default action.
	 *
	 * @param mixed $input
	 * @return array
	 */
	protected function _toUrl($input) {
		$url = (is_array($input)) ? array('action' => current($input)) : array('action' => $input);
		return $url;
	}

}
?>