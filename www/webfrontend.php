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

	public function __construct($kb_file)
	{
		$this->solver = new Solver();

		$this->getState($kb_file);
	}

	public function main()
	{
		try
		{
			$this->state = $this->getState();

			if (isset($_POST['answer']))
				$this->state->apply(_decode($_POST['answer']));

			$step = $this->solver->solveAll($this->state);

			$page = new Template('templates/layout.phtml');

			if ($step instanceof AskedQuestion) {
				$this->question = $step;
				$page->content = $this->display('templates/question.phtml');
			} else {
				$page->content = $this->display('templates/completed.phtml');
			}
			}
		}
		catch (Exception $e)
		{
			$page = new Template('templates/exception.phtml');
			$page->exception = $e;
		}

		$page->state = $this->state;

		echo $page->render();
	}

	private function display($template_file)
	{
		$template = new Template($template_file);

		$template->state = $this->state;

		$template->question = $this->question;

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
}

header('Content-Type: text/html; charset=UTF-8');
$frontend = new WebFrontend(current_kb());
$frontend->main();
