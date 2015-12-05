<?php

/**
 * <checklist_item name="hand_burn">
 *    <description>
 *	  Are children able to reach hot cooking plates?
 *    </description>
 *    <advice>
 *	  Restrict the children's access to the kitchen.
 *    </advice>
 * </checklist_item>
 */
class ChecklistItem
{
	// security level constants
	const A1 = "A1";

	const A2 = "A2";

	const B1 = "B1";

	const B2 = "B2";

	const C  = "C";

	// risk_factor and potential_injury constants
	const NEGLIGABLE = 0;

	const SMALL	 = 1;

	const GREAT	 = 2;

	public $name;

	public $description;

	public $advice;

	private $risk_factor;

	private $potential_injury;

	private $security_level;

	public $line_number;

	public function __construct()
	{
		$this->risk_factor = ChecklistItem::NEGLIGABLE;
		$this->potential_injury = ChecklistItem::SMALL;
	}

	/**
	 * @param $rf ChecklistItem-constant
	 * @return void
	 * @throws InvalidArgumentException if $rf is neither ChecklistItem::SMALL
	 * nor ChecklistItem::GREAT nor ChecklistItem::NEGLIGABLE
	 */
	public function setRiskFactor($rf)
	{
		if ($rf != ChecklistItem::SMALL &&
			$rf != ChecklistItem::GREAT &&
			$rf != ChecklistItem::NEGLIGABLE ) {
			throw new InvalidArgumentException("RiskFactor can"
				. " only be ChecklistItem::SMALL,"
				. " ChecklistItem::GREAT or "
				. " ChecklistItem::NEGLIGABLE.");
		}
		$this->risk_factor = $rf;
		$this->calcSecurityLevel();
	}

	/**
	 * @param $pi ChecklistItem-constant
	 * @return void
	 * @throws InvalidArgumentException if $pi is neither ChecklistItem::SMALL
	 * nor ChecklistItem::GREAT
	 */
	public function setPotentialInjury($pi)
	{
		if ($pi != ChecklistItem::SMALL && $pi != ChecklistItem::GREAT) {
			throw new InvalidArgumentException("PotentialInjury can"
				. " only be ChecklistItem::SMALL or"
				. " ChecklistItem::GREAT.");
		}
		$this->potential_injury = $pi;
		$this->calcSecurityLevel();
	}

	/**
	 * Calculate the $security_level of this ChecklistItem from its
	 * $risk_factor and $potential_injury.
	 *
	 * @return void
	 */
	private function calcSecurityLevel()
	{
		switch($this->risk_factor)
		{
		case ChecklistItem::GREAT:
			if ($this->potential_injury == ChecklistItem::GREAT) {
				$this->security_level = ChecklistItem::A1;
			} else if ($this->potential_injury == ChecklistItem::SMALL) {
				$this->security_level = ChecklistItem::A2;
			}
			break;

		case ChecklistItem::SMALL:
			if ($this->potential_injury == ChecklistItem::GREAT) {
				$this->security_level = ChecklistItem::B1;
			} else if ($this->potential_injury == ChecklistItem::SMALL) {
				$this->security_level = ChecklistItem::B2;
			}
			break;

		case ChecklistItem::NEGLIGABLE:
			$this->security_level = ChecklistItem::C;
			break;

		default: throw new RuntimeException("RiskFactor was set "
			. "to unknown value.");
		}
	}

	/**
	 * @return value in the range [ChecklistItem::A1,...,ChecklistItem::C]
	 * dependent on the risk_factor and potential_injury of this
	 * ChecklistItem
	 */
	public function getSecurityLevel()
	{
		return $this->security_level;
	}
}
