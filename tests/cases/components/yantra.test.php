<?php
App::import('Component', array('Yantra.Yantra', 'Session'));
App::import('Core', 'AppController');

class YantraTest extends CakeTestCase {

	public function setUp() {
		Mock::generate('AppController', 'TestController');
		Mock::generate('SessionComponent', 'MockSession');

		$this->Controller = new TestController();
		$this->StateMachine = new YantraComponent();
		$this->StateMachine->Session = new MockSession();

		$this->states = array(
			'signing in' => array(
				'sign_in',
				'new_user'
			),
			'billing' => 'billing',
			'paying' => 'payment',
			'reviewing' => 'summary',
			'processing' => 'fulfill'
		);

		$this->transitions = array(
			'sign in' => array(
				'signing in' => 'billing',
				'paying' => 'billing',
				'reviewing' => 'billing',
			),
			'bill & ship' => array(
				'billing'  => 'paying',
			),
			'pay' => array(
				'billing' => 'paying'
			),
			'review' => array(
				'paying' => 'reviewing'
			),
			'process' => array(
				'reviewing' => 'processing'
			)
		);

	}

	public function tearDown() {
		unset($this->Controller, $this->StateMachine, $this->states, $this->transitions);
	}

	protected function _defaultConfiguration() {
		$this->StateMachine->initialize(&$this->Controller);
		$this->StateMachine->states  = $this->states;
		$this->StateMachine->transitions = $this->transitions;
		$this->StateMachine->startup(&$this->Controller);

	}

	public function testInstance() {
		$this->assertTrue($this->StateMachine instanceof YantraComponent);
		$this->assertEqual($this->StateMachine->components, array('Session'));
	}

	public function testInitialize() {
		$this->StateMachine->initialize(&$this->Controller);
		$this->assertTrue($this->StateMachine->controller instanceof TestController);
	}

	public function testInitializeSettings() {
		$settings = array(
			'states' => $this->states,
			'transitions' => $this->transitions
		);

		$this->StateMachine->initialize(&$this->Controller, $settings);
		$this->assertEqual($this->StateMachine->states, $this->states);
		$this->assertEqual($this->StateMachine->transitions, $this->transitions);
	}

	public function testStartupWithNoStates() {
		$this->StateMachine->initialize(&$this->controller);
		$this->expectError(new PatternExpectation("/You must specify at least one state for the Yantra state machine/i"));

		$this->StateMachine->startup(&$this->Controller);
	}

	public function testStartupWithDefaultState() {
		$this->_defaultConfiguration();

		$this->StateMachine->Session->setReturnValue('check', true, array('StateMachine.current_state'));
		$this->StateMachine->Session->setReturnValue('write', true, array('StateMachine.current_state'));
		$this->StateMachine->Session->expectCallCount('check', 2);
	}

	public function testStartupOnInvalidTransition() {
		$this->StateMachine->initialize(&$this->Controller);
		$this->StateMachine->states  = $this->states;
		$this->StateMachine->transitions = $this->transitions;
		$this->Controller->action = 'billing';

		$this->StateMachine->Session->setReturnValue('read', 'reviewing', array('StateMachine.current_state'));
		$this->StateMachine->Session->setReturnValue('write', true, array('StateMachine.current_state'));
		$this->StateMachine->controller->expectOnce('redirect', array(array('action' => 'sign_in')));
		$this->StateMachine->Session->expectOnce('setFlash');
		$this->StateMachine->startup(&$this->Controller);
	}

	public function testGetCurrentState() {
		$this->_defaultConfiguration();

		$this->StateMachine->Session->setReturnValue('read', 'signing in', array('StateMachine.current_state'));
		$result = $this->StateMachine->state();
		$expected = 'signing in';

		$this->assertTrue($result, $expected);
	}

	public function testGetAllStates() {
		$this->_defaultConfiguration();

		$results = $this->StateMachine->states();
		$this->assertEqual($results, $this->states);
	}


	public function testTransition() {
		$this->_defaultConfiguration();

		$this->StateMachine->Session->setReturnValue('read', 'signing in', array('StateMachine.current_state'));
		$this->StateMachine->Session->setReturnValue('write', true, array('StateMachine.current_state', 'billing'));
		$result = $this->StateMachine->transition('signing in', 'billing');
		$this->assertTrue($result);
	}

	public function testInvalidTransition() {
		$this->_defaultConfiguration();

		$this->StateMachine->Session->setReturnValue('read', 'signing in', array('StateMachine.current_state'));
		$this->StateMachine->Session->setReturnValue('write', true, array('StateMachine.current_state', 'invalid state'));
		$result = $this->StateMachine->transition('signing in', 'invalid state');
		$this->assertFalse($result);
	}

	public function testTransitionWithoutCurrentStateBeingSet() {
		$this->_defaultConfiguration();

		$this->StateMachine->Session->setReturnValue('read', false, array('StateMachine.current_state'));
		$result = $this->StateMachine->transition('signing in', 'billing');
		$this->assertFalse($result);
	}

	public function testEventWithAutoRedirect() {
		$this->_defaultConfiguration();
		$this->StateMachine->auto = true;

		$this->StateMachine->Session->setReturnValue('read', 'signing in', array('StateMachine.current_state'));
		$this->StateMachine->Session->setReturnValue('write', true, array('StateMachine.current_state', 'billing'));
		$this->StateMachine->controller->expectOnce('redirect', array(array('action' => 'billing')));

		$result = $this->StateMachine->event('sign in');
	}

	public function testNonExistentEventWithAutoRedirect() {
		$this->_defaultConfiguration();
		$this->StateMachine->auto = true;
		$this->StateMachine->Session->setReturnValue('read', 'signing in', array('StateMachine.current_state'));

		$result = $this->StateMachine->event('invalid event');
		$this->assertFalse($result);
	}
}

?>