Yantra Component
================

This component allows you to specify sets of states, transitions and events that
comprise the foundation of a finite state machine.

What's a finite state machine, you ask? Well if you're using this component,
you probably already have an idea of how they work. If not, then there is more
information available in the *Resources & References* section below.

Use
---

An example configuration:

	public $components = array(
		'Yantra.StateMachine' => array(
			'auto' => true,
			'default' => 'signing in',
			'states' => array(
				'signing in' => array('sign_in', 'new_user'),
				'billing' => 'billing',
				'paying' => 'payment',
				'reviewing' => 'summary',
				'processing' => 'fulfill'
			),
			'transitions' => array(
				'sign in' => array('signing in' => 'billing'),
				'bill & ship' => array('billing' => 'paying'),
				'pay' => array('paying' => 'reviewing'),
				'review' => array('reviewing' => 'processing'),
			)
		)
	);

This defines a finite state machine with a total of 5 states ('signing in', 'billing',
'paying', 'reviewing' and 'processing'). The values that correspond to these keys are the controller
`actions` which correspond to this state. As a result of this formulation, a state may be comprised
of more than one action.

The `transitions` are then defined, which are the allowed paths that a state-change can take.
In the example above the keys under `transitions` are the `events`, and the values attached to these
events are key/value pairs of valid state transitions.

For example, it is possible to transition from the `paying` to the `reviewing` state (_the 'pay' event_),
but it is not possible to transition from `paying` to `sign in`. We would call this an _invalid state transition_.
Attempting to travel along a forbidden transition path will result in a redirect back to the origin state.

API
---

YantraComponent::transition($from, $to): Transition from one state to another
YantraComponent::transitions(): Get all defined transitions.
YantraComponent::event($events): Manually trigger an event to cause a transition.
YantraComponent::events(): Get all defined events.
YantraComponent::state($state): Get the current state.
YantraComponent::states(): Get all defined states.


See the doc blocks for more specifics on the API, and the test cases for their intended usage.


Resources & References
----------------------
  - The [Wikipedia entry on Finite State Machines](http://en.wikipedia.org/wiki/Finite-state_machine).
  - A simple, [understandable description](http://www.lamsonproject.org/docs/introduction_to_finite_state_machines.html) of finite state machines by Zed Shaw.