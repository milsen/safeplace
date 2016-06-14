<?php

define('STATE_UNDEFINED', 'undefined');

/**
 * Een rule waarmee een fact gevonden kan worden.
 *
 * <rule>
 *     [<description>]
 *     <if/>
 *     <then/>
 * </rule>
 */
class Rule
{
	public $inferred_facts;

	public $description;

	public $condition;

	public $consequences = array();

	public $priority = 0;

	public $line_number;

	public function __construct()
	{
		$this->inferred_facts = new Set();
	}

	public function infers($fact)
	{
		return $this->inferred_facts->contains($fact);
	}

	public function __toString()
	{
		return sprintf('[Rule "%s" (line %d)]',
			$this->description,
			$this->line_number);
	}
}

/**
 * Een vraag waarmee een antwoord op $inferred_facts kan worden gevonden.
 *
 * <question>
 *     <description/>
 *     <option/>
 * </question>
 */
class Question
{
	public $inferred_facts;

	public $description;

	public $options = array();

	public $priority = 0;

	public $line_number;

	public function __construct()
	{
		$this->inferred_facts = new Set();
	}

	public function infers($fact)
	{
		return $this->inferred_facts->contains($fact);
	}

	public function __toString()
	{
		return sprintf('[Question: %s]', $this->description);
	}
}

/**
 * Een mogelijk antwoord op een Question.
 *
 * <option>
 *     <description/>
 *     <then/>
 * </option>
 */
class Option
{
	public $description;

	public $consequences = array();
}

interface Condition
{
	public function evaluate(KnowledgeState $state);

	public function asArray();
}

/**
 * <and>
 *     Conditions, e.g. <fact/>
 * </and>
 */
class WhenAllCondition implements Condition
{
	public $conditions;

	public function __construct()
	{
		$this->conditions = new Set();
	}

	public function addCondition(Condition $condition)
	{
		$this->conditions->push($condition);
	}

	public function evaluate(KnowledgeState $state)
	{
		// assumptie: er moet ten minste één conditie zijn
		assert('count($this->conditions) > 0');

		$values = array();
		foreach ($this->conditions as $condition)
			$values[] = $condition->evaluate($state);

		// Als er minstens één Nee bij zit, dan iig niet.
		$nos = array_filter_type('No', $values);
		if (count($nos) > 0)
			return No::because($nos);

		// Als er een maybe in zit, dan nog steeds onzeker.
		$maybes = array_filter_type('Maybe', $values);
		if (count($maybes) > 0)
			return Maybe::because($maybes);

		return Yes::because($values);
	}

	public function asArray()
	{
		return array($this, array_map_method('asArray', $this->conditions));
	}
}

/**
 * <or>
 *     Conditions, e.g. <fact/>
 * </or>
 */
class WhenAnyCondition implements Condition
{
	public $conditions;

	public function __construct()
	{
		$this->conditions = new Set();
	}

	public function addCondition(Condition $condition)
	{
		$this->conditions->push($condition);
	}

	public function evaluate(KnowledgeState $state)
	{
		// assumptie: er moet ten minste één conditie zijn
		assert('count($this->conditions) > 0');

		$values = array();
		foreach ($this->conditions as $condition)
			$values[] = $condition->evaluate($state);

		// Is er een ja, dan is dit zeker goed.
		$yesses = array_filter_type('Yes', $values);
		if ($yesses)
			return Yes::because($yesses);

		// Is er een misschien, dan zou dit ook goed kunnen zijn
		$maybes = array_filter_type('Maybe', $values);
		if ($maybes)
			return Maybe::because($maybes);

		// Geen ja's, geen misschien's, dus alle condities gaven No terug.
		return No::because($values);
	}

	public function asArray()
	{
		return array($this, array_map_method('asArray', $this->conditions));
	}
}

/**
 * <not>
 *     Condition, e.g. <fact/>
 * </not>
 */
class NegationCondition implements Condition
{
	public $condition;

	public function __construct(Condition $condition)
	{
		$this->condition = $condition;
	}

	public function evaluate(KnowledgeState $state)
	{
		return $this->condition->evaluate($state)->negate();
	}

	public function asArray()
	{
		return array($this, $this->condition->asArray());
	}
}

/**
 * <fact name="fact_name">value</fact>
 */
class FactCondition implements Condition
{
	public $name;

	public $value;

	public function __construct($name, $value)
	{
		$this->name = trim($name);
		$this->value = trim($value);
	}

	public function evaluate(KnowledgeState $state)
	{
		$state_value = $state->value($this->name);

		if ($state_value instanceof Maybe)
			return $state_value;

		return $state_value == $this->value
			? Yes::because([$this->name])
			: No::because([$this->name]);
	}

	public function asArray()
	{
		return array($this);
	}
}

/**
 * <building name="swimming_bath" value="Yes">
 *    <title>Swimming Bath</title>
 *    <description>
 *        A swimming bath yields many dangers for children.
 *    </description>
 *    <risk name="drown">
 *    <risk name="burning_eyes">
 * </building>
 */
class Building
{
	public $name;

	public $title;

	public $description;

	public $value;

	public $risks;

	public $line_number;

	public function __construct()
	{
		$this->risks = new Set();
	}
}

abstract class TruthState
{
	public $factors;

	public function __construct(Traversable $factors)
	{
		$this->factors = $factors;
	}

	public function __toString()
	{
		return sprintf("[%s because: %s]",
			get_class($this),
			implode(', ', array_map('strval', iterator_to_array($this->factors))));
	}

	abstract public function negate();

	static public function because($factors = null)
	{
		if (is_null($factors))
			$factors = new EmptyIterator();

		elseif (is_scalar($factors))
			$factors = new ArrayIterator([$factors]);

		elseif (is_array($factors))
			$factors = new ArrayIterator($factors);

		$called_class = get_called_class();
		return new $called_class($factors);
	}
}

class Yes extends TruthState
{
	public function negate()
	{
		return new No($this->factors);
	}
}

class No extends TruthState
{
	public function negate()
	{
		return new Yes($this->factors);
	}
}

class Maybe extends TruthState
{
	public function negate()
	{
		return new Maybe($this->factors);
	}

	public function causes()
	{
		// Hier wordt de volgorde van de vragen effectief bepaald!
		// We kijken naar alle factoren die ervoor zorgden dat de vraag niet
		// beantwoord kon worden, welke het meest invloedrijk is, en sorteren
		// daarop om te zien waar we verder mee moeten.
		// (Deze implementatie is zeker voor verbetering vatbaar.)
		$causes = $this->divideAmong(1.0, $this->factors)->data();

		// grootst verantwoordelijk ontbrekend fact op top.
		asort($causes);

		$causes = array_reverse($causes);

		return array_keys($causes);
	}

	private function divideAmong($percentage, Traversable $factors)
	{
		$effects = new Map(0.0);

		// als er geen factors zijn, dan heeft het ook geen zin
		// de verantwoordelijkheid per uit te rekenen.
		if (count($factors) == 0)
			return $effects;

		// iedere factor op hetzelfde niveau heeft evenveel invloed.
		$percentage_per_factor = $percentage / count($factors);

		foreach ($factors as $factor)
		{
			// recursief de hoeveelheid invloed doorverdelen en optellen bij het totaal per factor.
			if ($factor instanceof TruthState)
				foreach ($this->divideAmong($percentage_per_factor, $factor->factors) as $factor_name => $effect)
					$effects[$factor_name] += $effect;
			else
				$effects[$factor] += $percentage_per_factor;
		}

		return $effects;
	}
}

/**
 * Een knowledge base op een bepaald moment. Via KnowledgeState::apply kunnen er
 * nieuwe feiten aan de state toegevoegd worden (en wordt het stieken een nieuwe
 * state).
 */
class KnowledgeState
{
	public $title;

	public $description;

	public $facts;

	public $rules;

	public $questions;

	public $buildings;

	public $solved;

	public $goalStack;

	public function __construct()
	{
		$this->facts = array(
			'undefined' => STATE_UNDEFINED
		);

		$this->rules = new Set();

		$this->questions = new Set();

		$this->buildings = new Set();

		$this->solved = new Set();

		$this->goalStack = new Stack();
	}

	/**
	 * Past $consequences toe op de huidige $state, en geeft dat als nieuwe state terug.
	 * Alle $consequences krijgen $reason als reden mee.
	 *
	 * @return KnowledgeState
	 */
	public function apply(array $consequences)
	{
		$this->facts = array_merge($this->facts, $consequences);
	}

	public function value($fact_name)
	{
		$fact_name = $this->resolve($fact_name);

		if (!isset($this->facts[$fact_name]))
			return Maybe::because([$fact_name]);

		return $this->resolve($this->facts[$fact_name]);
	}

	public function resolve($value)
	{
		$stack = array();

		while (self::is_variable($value))
		{
			if (in_array($value, $stack))
				throw new RuntimeException("Infinite recursion when trying to retrieve fact '$value' after I retrieved " . implode(', ', $stack) . ".");

			$stack[] = $value;

			if (isset($this->facts[self::variable_name($value)]))
				$value = $this->facts[self::variable_name($value)];
			else
				return self::variable_name($value);
		}

		return $value;
	}

	public function substitute_variables($text, $formatter = null)
	{
		$callback = function($match) use ($formatter) {
			$value = $this->value($match[1]);

			if ($value instanceof Maybe)
				return $match[0];

			if ($formatter)
				$value = call_user_func_array($formatter, [$value]);

			return $value;
		};

		return preg_replace_callback('/\$([a-z][a-z0-9_]*)\b/i', $callback, $text);
	}

	public function getDeducedBuildings()
	{
		$deduced_buildings = new Set();

		foreach ($this->buildings as $building) {
			if ($this->value($building->name) == $building->value){
				$deduced_buildings->push($building);
			}
		}

		return $deduced_buildings;
	}

	static public function is_variable($fact_name)
	{
		return substr($fact_name, 0, 1) == '$';
	}

	static public function variable_name($fact_name)
	{
		return substr($fact_name, 1); // strip of the $
	}

	static public function is_default_fact($fact_name)
	{
		$empty_state = new self();
		return isset($empty_state->facts[$fact_name]);
	}
}

/**
 * Solver is een forward & backward chaining implementatie die op basis van
 * een knowledge base (een berg regels, mogelijke vragen en gaandeweg feiten)
 * blijft zoeken, regels toepassen en vragen kiezen totdat alle goals opgelost
 * zijn. Gebruik Solver::solveAll(state) tot deze geen vragen meer teruggeeft.
 */
class Solver
{

	/**
	 * Probeer gegeven een initiële $knowledge state en een lijst van $goals
	 * zo veel mogelijk $goals op te lossen. Dit doet hij door een stack met
	 * goals op te lossen. Als een goal niet op te lossen is, kijkt hij naar
	 * de meest primaire reden waarom (Maybe::$factors) en voegt hij die factor
	 * op top van de goal stack.
	 * Als een goal niet op te lossen is omdat er geen vragen/regels meer voor
	 * zijn geeft hij een Notice en gaat hij verder met de andere goals op de
	 * stack.
	 *
	 * @param KnowledgeState $knowledge begin-state
	 * @return Question | null
	 */
	public function solveAll(KnowledgeState $state)
	{
		// herhaal zo lang er goals op de goal stack zitten
		while (!$state->goalStack->isEmpty())
		{

			// probeer het eerste goal op te lossen
			$result = $this->solve($state, $state->goalStack->top());

			// Oh, dat resulteerde in een vraag. Stel hem (of geef hem terug om
			// de interface hem te laten stellen eigenlijk.)
			if ($result instanceof Question)
			{
				return $result;
			}

			// Goal is niet opgelost, het antwoord is nog niet duidelijk.
			elseif ($result instanceof Maybe)
			{
				// waarom niet? $causes bevat een lijst van facts die niet
				// bekend zijn, dus die willen we proberen op te lossen.
				$causes = $result->causes();

				// echo '<pre>', print_r($causes, true), '</pre>';

				// er zijn facts die nog niet zijn afgeleid
				while (count($causes) > 0)
				{
					// neem het meest invloedrijke fact, leidt dat af
					$main_cause = array_shift($causes);

					// meest invloedrijke fact staat al op todo-lijst?
					// sla het over.
					// TODO: misschien beter om juist naar de top te halen?
					// en dan dat opnieuw proberen te bewijzen?
					if (iterator_contains($state->goalStack, $main_cause))
						continue;

					// Het kan niet zijn dat het al eens is opgelost. Dan zou hij
					// in facts moeten zitten.
					assert('!$state->solved->contains($main_cause)');

					// zet het te bewijzen fact bovenaan op de todo-lijst.
					$state->goalStack->push($main_cause);

					// .. en spring terug naar volgende goal op goal-stack!
					continue 2;
				}

				// Er zijn geen redenen waarom het goal niet afgeleid kon worden? Ojee!
				if (count($causes) == 0)
				{
					// Haal het onbewezen fact van de todo-lijst
					$unsatisfied_goal = $state->goalStack->pop();


					// en markeer hem dan maar als niet waar (closed-world assumption?)
					$state->apply(array($unsatisfied_goal => STATE_UNDEFINED));

					// compute the effects of this change by applying the other rules
					$this->forwardChain($state);

					$state->solved->push($unsatisfied_goal);
				}
			}

			// Yes, het is gelukt om een Yes of No antwoord te vinden voor dit goal.
			// Mooi, dan kan dat van de te bewijzen stack af.
			else
			{
				// aanname: als het goal kon worden afgeleid, dan is het nu deel van
				// de afgeleide kennis.
				assert('isset($state->facts[$state->goalStack->top()])');

				// op naar het volgende goal.
				$state->solved->push($state->goalStack->pop());

			}
		}
	}

	/**
	 * Solve probeert één $goal op te lossen door regels toe te passen of
	 * relevante vragen te stellen. Als het lukt een regel toe te passen of
	 * een vraag te stellen geeft hij een nieuwe $state terug. Ook geeft hij
	 * de TruthState voor $goal terug. In het geval van Maybe kan dat gebruikt
	 * worden om af te leiden welk $goal als volgende moet worden afgeleid om
	 * verder te komen.
	 *
	 * @param KnowledgeState $state huidige knowledge state
	 * @param string goal naam van het fact dat wordt afgeleid
	 * @return TruthState | Question
	 */
	public function solve(KnowledgeState $state, $goal)
	{
		// Forward chain until there is nothing left to derive.
		$this->forwardChain($state);

		// Test whether the fact is already in the knowledge base and if not, if it is solely
		// unknown because we don't know the current goal we try to prove. Because, it could
		// have a variable as value which still needs to be resolved, but that might be a
		// different goal!
		$current_value = $state->value($goal);

		if (!($current_value instanceof Maybe && $current_value->factors == new ArrayIterator([$goal])))
			return $current_value;

		// Is er misschien een regel die we kunnen toepassen
		$relevant_rules = new CallbackFilterIterator($state->rules->getIterator(),
			function($rule) use ($goal) { return $rule->infers($goal); });

		// Assume that all relevant rules result in maybe's. If not, something went
		// horribly wrong in $this->forwardChain()!
		foreach ($relevant_rules as $rule)
			assert('$rule->condition->evaluate($state) instanceof Maybe');

		// Is er misschien een directe vraag die we kunnen stellen?
		$relevant_questions = new CallbackFilterIterator($state->questions->getIterator(),
			function($question) use ($goal) { return $question->infers($goal); });

		// If this problem can be solved by a rule, use it!
		if (iterator_count($relevant_rules) > 0)
			return Maybe::because(new CallbackMapIterator($relevant_rules, function($rule) use ($state) {
				return $rule->condition->evaluate($state);
			}));

		// If not, but when we do have a question to solve it, use that instead.
		if (iterator_count($relevant_questions) > 0)
		{
			$question = iterator_first($relevant_questions);

			// haal de vraag hoe dan ook uit de mogelijk te stellen vragen. Het heeft geen zin
			// om hem twee keer te stellen.
			$state->questions->remove($question);

			return $question;
		}

		// We have no idea how to solve this. No longer our problem!
		// (The caller should set $goal to undefined or something.)
		return Maybe::because();
	}

	public function forwardChain(KnowledgeState $state)
	{
		while (!$state->rules->isEmpty())
		{
			foreach ($state->rules as $rule)
			{
				$rule_result = $rule->condition->evaluate($state);

				// If a rule could be applied, remove it to prevent it from being
				// applied again.
				if ($rule_result instanceof Yes or $rule_result instanceof No)
					$state->rules->remove($rule);

				// If the rule was true, add the consequences, the inferred knowledge
				// to the knowledge state and continue applying rules on the new knowledge.
				if ($rule_result instanceof Yes)
				{
					$state->apply($rule->consequences);
					continue 2;
				}
			}

			// None of the rules changed the state: stop trying.
			break;
		}
	}
}
