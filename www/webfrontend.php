<?php

include '../util.php';
include '../solver.php';
include '../reader.php';
include '../checklist.php';

function _encode($data)
{
	return base64_encode(gzcompress(serialize($data)));
}

function _decode($data)
{
	return unserialize(gzuncompress(base64_decode($data)));
}

class WebFrontend
{

	private $solver;

	private $state;

	private $checklist;

	private $question;

	private $deduced_buildings;

	public function __construct($kb_file)
	{
		$this->solver = new Solver();

		$this->getState($kb_file);
	}

	public function main()
	{
		try
		{
			// if the user wants to downlaod the checklist, let him
			if (isset($_POST['action']) &&
				$_POST['action'] == 'download_checklist') {

				$csv_file = $this->prepareCsvChecklist();

				$filename = date("d-m-Y_H-i-s") . '.csv';

				header('Content-Type: text/csv');
				header(
					'Content-Disposition: attachment; ' .
					'filename=safeplace_checklist_' .
					$filename
				);
				echo $csv_file;
				exit;
			}

			$page = new Template('templates/layout.phtml');

			// if a checklist was filled out, we process it and
			// display the conclusion
			if (isset($_POST['radioGroup'])) {

				$this->setChecklistAttributes($_POST['radioGroup']);

				$this->checklist->removeBySecurityLevel(ChecklistItem::C);

				$this->checklist->sortBySecurityLevel();

				$page->content = $this->display('templates/completed.phtml');

			} else {

				// if an answer to a question given, use the answer for
				// further fact deduction
				if (isset($_POST['answer'])) {
					$this->state->apply(_decode($_POST['answer']));
				}

				$step = $this->solver->solveAll($this->state);

				if ($step instanceof Question) {
					$this->question = $step;
					$page->content = $this->display('templates/question.phtml');
				} else {
					$this->deduced_buildings = $this->state->getDeducedBuildings();
					$this->checklist->create($this->deduced_buildings);
					$this->checklist->sortByDescription();
					$page->content = $this->display('templates/checklist.phtml');
				}
			}
		}
		catch (Exception $e)
		{
			$page = new Template('templates/exception.phtml');
			$page->exception = $e;
		}

		$page->state = $this->state;

		$page->checklist = $this->checklist;

		echo $page->render();
	}

	private function display($template_file)
	{
		$template = new Template($template_file);

		$template->state = $this->state;

		$template->checklist = $this->checklist;

		$template->question = $this->question;

		$template->deduced_buildings = $this->deduced_buildings;

		return $template->render();
	}

	private function getState($kb_file)
	{
		if (!isset($_POST['state']) || !isset($_POST['checklist'])) {
			$reader = new KnowledgeBaseReader();
			$kb = $reader->parse($kb_file);
		}

		if (isset($_POST['state'])) {
			$this->state = _decode($_POST['state']);
		} else {
			$this->state = $kb[0];
			$this->pushGoals();
		}

		if (isset($_POST['checklist'])) {
			$this->checklist = _decode($_POST['checklist']);
		} else {
			$this->checklist = $kb[1];
		}
	}

	private function pushGoals()
	{
		if (!empty($_GET['goals'])) {
			foreach (explode(',', $_GET['goals']) as $goal) {
				$this->state->goalStack->push($goal);
			}
		} else {
			foreach ($this->state->buildings as $building) {
				$this->state->goalStack->push($building->name);

				// Also push the building's answer value (if it
				// is a variable) as a goal to be solved.
				if (KnowledgeState::is_variable($building->value)) {
					$this->state->goalStack->push(KnowledgeState::variable_name($building->value));
				}
			}
		}
	}

	/**
	 * Set the $risk_factor and $potential_injury of $this->checklist using
	 * the given $checklist_answers.
	 *
	 * @param $checklist_answers array in which two following fields contain
	 * the $risk_factor and $potential_injury of one ChecklistItem
	 */
	private function setChecklistAttributes($checklist_answers)
	{
		foreach ($checklist_answers as $i => $checklist_answer) {
			// two radio groups for one ChecklistItem
			$checklist_item = $this->checklist->elem((int)($i/2));

			// the first radio group concerns the risk factor,
			// the second one always concerns the potential injury
			if ($i % 2 == 0) {
				$checklist_item->setRiskFactor($checklist_answer);
			} else {
				$checklist_item->setPotentialInjury($checklist_answer);
			}
		}
	}

	/**
	 * Create a csv-file in the stream 'php://output' using asCsvArray() of
	 * $this->checklist and return the stream's contents.
	 *
	 * @return string csv-file that was created from $this->checklist
	 */
	private function prepareCsvChecklist()
	{
		$csv = $this->checklist->asCsvArray();

		$csv_file = fopen('php://output', 'w');

		ob_start();

		foreach ($csv as $row) {
			fputcsv($csv_file, $row);
		}

		fclose($csv_file);

		return ob_get_clean();
	}
}

header('Content-Type: text/html; charset=UTF-8');
$frontend = new WebFrontend(current_kb());
$frontend->main();
