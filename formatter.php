<?php

class HTMLFormatter
{
	private $state;

	private $checklist;

	public function __construct(KnowledgeState $state, Checklist $checklist)
	{
		$this->state = $state;

		$this->checklist = $checklist;
	}

	public function formatRules()
	{
		$rule_table = '';

		foreach ($this->state->rules as $rule) {
			$rule_table .= sprintf('<section>%s</section>', $this->formatRule($rule));
		}

		return $rule_table;
	}

	private function formatRule(Rule $rule)
	{
		return sprintf('
			<table class="kb-rule">
				<tr>
					<th colspan="2" class="kb-rule-description">
						<span class="line-number">line %d</span>
						%s
					</th>
				</tr>
				<tr>
					<th class="keyword">If</th>
					<td>%s</td>
				</tr>
				<tr>
					<th class="keyword">Then</th>
					<td>%s</td>
				</tr>
			</table>',
				$rule->line_number,
				$this->escape($rule->description),
				$this->formatCondition($rule->condition),
				$this->formatConsequence($rule->consequences));
	}

	private function formatConsequence(array $consequences)
	{
		$rows = array();

		foreach ($consequences as $name => $value)
			$rows[] = sprintf('<tr><td>%s</td><th>:=</th><td>%s</td></tr>',
				$this->escape($name), $this->escape($value));

		return sprintf('<table class="kb-consequence">%s</table>', implode("\n", $rows));
	}

	private function formatCondition(Condition $condition)
	{
		switch (get_class($condition))
		{
			case 'WhenAllCondition':
				return $this->formatComplexCondition($condition,"AND");

			case 'WhenAnyCondition':
				return $this->formatComplexCondition($condition,"OR");

			case 'NegationCondition':
				return $this->formatComplexCondition($condition,"NOT");

			case 'FactCondition':
				return $this->formatFactCondition($condition);

			default:
				return $this->formatUnknownCondition($condition);
		}
	}

	private function formatUnknownCondition(Condition $condition)
	{
		return sprintf('<pre class="evaluation-%s">%s</pre>',
			$this->evaluatedValue($condition),
			$this->escape(strval($condition)));
	}

	private function formatComplexCondition(Condition $condition, $keyword)
	{
		// for NegationCondition, get negated condition
		if ($keyword == "NOT") {
			$content = $this->formatCondition($condition->condition);

		// for When*Condition, get combined conditions
		} else {
			$content = array_map(
				function($condition) {
					return sprintf('<tr><td>%s</td></tr>',
						$this->formatCondition($condition));
				},
				iterator_to_array($condition->conditions));

			$content = sprintf('<table>%s</table>', implode("\n", $content));
		}

		return sprintf('
			<table class="kb-complex-condition kb-condition evaluation-%s">
				<tr>
					<th>%s</th>
					<td>%s</td>
				</tr>
			</table>',
				$this->evaluatedValue($condition),
				$keyword,
				$content);
	}

	private function formatFactCondition(FactCondition $condition)
	{
		return sprintf('
			<table class="kb-fact-condition kb-condition evaluation-%s">
				<tr>
					<td>%s</td><th>=</th><td>%s</td>
				</tr>
			</table>',
				$this->evaluatedValue($condition),
				$this->escape($condition->name),
				$this->escape($condition->value));
	}

	public function formatBuildings()
	{
		$building_table = '';

		foreach ($this->state->buildings as $building) {
			$building_table .= sprintf('<section>%s</section>', $this->formatBuilding($building));
		}

		return $building_table;
	}

	private function formatBuilding(Building $building)
	{
		return sprintf('
			<table class="kb-building">
				<tr>
					<th class="kb-building-title">
						%s
						<span class="line-number">line %d</span>
					</th>
				</tr>
				<tr>
					<th class="kb-building-title">
						<span class="name-value-pair">%s</span>
					</th>
				</tr>
				<tr>
					<td>%s</td>
				</tr>
			</table>',
				$this->escape($building->title),
				$building->line_number,
				$this->formatFactCondition(
					new FactCondition($building->name,$building->value)),
				$this->formatChecklistItems($building->risks));
	}

	private function formatChecklistItems($risks)
	{
		$checklist_item_table = '';

		foreach ($risks as $risk) {
			foreach ($this->checklist as $checklist_item) {
				if ($checklist_item->name == $risk) {
					$checklist_item_table .= sprintf('<tr><td>%s</td></tr>',
						$this->formatChecklistItem($checklist_item));
					break;
				}
			}
		}

		return $checklist_item_table;
	}

	private function formatChecklistItem($checklist_item)
	{
		return sprintf('
			<table class="kb-checklist-item">
				<tr>
					<th colspan="2" class="kb-checklist-item-header">
						%s
						<span class="line-number">line %d</span>
					</th>
				</tr>
				<tr>
					<th class="keyword">Q</th>
					<td>%s</td>
				</tr>
				<tr>
					<th class="keyword">A</th>
					<td>%s</td>
				</tr>
			</table>',
				$this->escape($checklist_item->name),
				$checklist_item->line_number,
				$this->escape($checklist_item->description),
				$this->escape($checklist_item->advice));
	}

	private function evaluatedValue(Condition $condition)
	{
		if (!$this->state)
			return 'unknown';

		$value = $condition->evaluate($this->state);

		if ($value instanceof Yes)
			return 'true';

		elseif ($value instanceof No)
			return 'false';

		elseif ($value instanceof Maybe)
			return 'maybe';

		else
			return 'undefined';
	}

	private function escape($text)
	{
		return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
	}
}
