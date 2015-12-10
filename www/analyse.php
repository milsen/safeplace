<?php

include '../util.php';
include '../solver.php';
include '../reader.php';
include '../formatter.php';
include '../checklist.php';

$reader = new KnowledgeBaseReader;
$kb = $reader->parse(current_kb());
$state = $kb[0];
$checklist = $kb[1];

class FactStatistics
{
	public $name;

	public $values;

	public function __construct($name)
	{
		$this->name = $name;

		$this->values = new Map(function() {
			return new FactValueStatistics();
		});
	}
}

class FactValueStatistics
{
	public $inferringRules;

	public $dependingRules;

	public $inferringQuestions;

	public function __construct()
	{
		$this->inferringRules = new Set();

		$this->dependingRules = new Set();

		$this->inferringQuestions = new Set();
	}
}

$stats = new Map(function($fact_name) {
	return new FactStatistics($fact_name);
});

foreach ($state->rules as $rule)
{
	$fact_conditions = array_filter_type('FactCondition',
		array_flatten($rule->condition->asArray()));
	
	foreach ($fact_conditions as $condition)
		$stats[$condition->name]
			->values[$condition->value]
			->dependingRules
			->push($condition->value);
	
	foreach ($rule->consequences as $fact_name => $value)
		$stats[$fact_name]
			->values[$value]
			->inferringRules
			->push($rule);
}

foreach ($state->questions as $question)
	foreach ($question->options as $option)
		foreach ($option->consequences as $fact_name => $value)
			$stats[$fact_name]
				->values[$value]
				->inferringQuestions
				->push($question);

foreach ($state->buildings as $building)
	foreach ($stats[$building->name]->values as $possible_value)
		$possible_value
			->dependingRules
			->push($building);

$template = new Template('templates/analyse.phtml');
$template->kb = $state;
$template->checklist = $checklist;
$template->stats = $stats;

echo $template->render();
