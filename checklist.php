<?php

/**
 * This is a sorted Set of ChecklistItems which store questions and advice
 * concerning safety measures and possible risks in a building.
 */
class Checklist extends Set
{
	/**
	 * Turn this Checklist of all possible ChecklistItems into one that only
	 * contains the ChecklistItems associated with Buildings deduced by the
	 * Solver.
	 *
	 * @param $deduced_buildings All Buildings that can be deduced from the
	 * current KnowledgeState.
	 * @return void
	 */
	public function create($deduced_buildings)
	{
		$relevant_risks = new Set();

		// for each building that was deduced from rules and facts,
		// store the names of its associated risks in relevant_risks
		foreach ($deduced_buildings as $building) {
			$relevant_risks->pushAll($building->risks);
		}

		// delete all checklist_items from our checklist whose names are
		// not in relevant_risks
		foreach ($this as $checklist_item) {
			if (!$relevant_risks->contains($checklist_item->name)) {
				parent::remove($checklist_item);
			}
		}
	}

	/**
	 * Remove all ChecklistItems from this Checklist that have the given
	 * security_level.
	 *
	 * @param $security_level
	 * @throws InvalidArgumentException if the given $security_level is not
	 * one of ChecklistItem::{A1,A2,B1,B2,C}
	 * @return void
	 */
	public function removeBySecurityLevel($security_level)
	{
		switch ($security_level)
		{
		case ChecklistItem::A1:
		case ChecklistItem::A2:
		case ChecklistItem::B1:
		case ChecklistItem::B2:
		case ChecklistItem::C:
			foreach ($this as $checklist_item) {
				if ($checklist_item->getSecurityLevel() == $security_level) {
					parent::remove($checklist_item);
				}
			}
			break;
		default:
			throw new InvalidArgumentException("The given " .
				"SecurityLevel is not one of the " .
				"ChecklistItem-constants A1,A2,B1,B2,C.");
		}
	}

	/**
	 * Sort this Checklist using strcmp() on the security_levels of the
	 * ChecklistItems.
	 */
	public function sortBySecurityLevel() {
		parent::sort(function($a, $b) {
			return strcmp(
				$a->getSecurityLevel(),
				$b->getSecurityLevel()
			);
		});
	}

	/**
	 * Sort this Checklist using strcmp() on the descriptions of the
	 * ChecklistItems.
	 */
	public function sortByDescription() {
		parent::sort(function($a, $b) {
			return strcmp(
				$a->description,
				$b->description
			);
		});
	}

	/**
	 * Create an array, a list of rows, that can be used for fputcsv() to
	 * produce a csv-file containing all ChecklistItems of this Checklist.
	 * It includes a header that can be filled out by a user that wants to
	 * perform a risk inventory.
	 *
	 * @return array of arrays (rows) to be used for fputcsv()
	 */
	public function asCsvArray() {

		// header of table
		$csv = array (
			array('Safeplace Risk Inventory'),
			array('pkt11.ikhoefgeen.nl'),
			array(''),
			array('Location:'),
			array('Contact Person:'),
			array('Address:'),
			array(''),
			array('Inventory Date:'),
			array('Carried out by:'),
			array(''),
			array(
				'Urgency',
				// 'Item Name',
				'Item',
				'Advice',
				'Action/Comment',
				'Deadline',
				'Responsible'
			)
		);

		// one row for each ChecklistItems
		foreach ($this as $checklist_item) {
			$csv[] = array(
				$checklist_item->getSecurityLevel(),
				// $checklist_item->name,
				$checklist_item->description,
				$checklist_item->advice
			);
		}

		return $csv;
	}
}

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
	const NEGLIGIBLE = 0;

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
		$this->risk_factor = ChecklistItem::NEGLIGIBLE;
		$this->potential_injury = ChecklistItem::SMALL;
	}

	/**
	 * @param $rf ChecklistItem-constant
	 * @return void
	 * @throws InvalidArgumentException if $rf is neither ChecklistItem::SMALL
	 * nor ChecklistItem::GREAT nor ChecklistItem::NEGLIGIBLE
	 */
	public function setRiskFactor($rf)
	{
		if ($rf != ChecklistItem::SMALL &&
			$rf != ChecklistItem::GREAT &&
			$rf != ChecklistItem::NEGLIGIBLE ) {
				throw new InvalidArgumentException("RiskFactor can"
					. " only be ChecklistItem::SMALL,"
					. " ChecklistItem::GREAT or "
					. " ChecklistItem::NEGLIGIBLE.");
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

		case ChecklistItem::NEGLIGIBLE:
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
