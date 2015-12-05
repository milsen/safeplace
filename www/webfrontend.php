<?php

include '../util.php';
include '../solver.php';
include '../reader.php';

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

	private $kb_file;

	private $question;

	public function __construct($kb_file)
	{
		$this->solver = new Solver();

		$this->kb_file = $kb_file;
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

	private function getState()
	{
		if (isset($_POST['state']))
			return _decode($_POST['state']);
		else
			return $this->createNewState();
	}

	private function createNewState()
	{
		$state = $this->readState($this->kb_file);

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

		return $state;
	}

	private function readState($file)
	{
		$reader = new KnowledgeBaseReader;
		$state = $reader->parse($file);

		return $state;
	}
}

header('Content-Type: text/html; charset=UTF-8');
$frontend = new WebFrontend(current_kb());
$frontend->main();
