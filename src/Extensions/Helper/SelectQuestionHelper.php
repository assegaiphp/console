<?php

namespace Assegai\Console\Extensions\Helper;

use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;

class SelectQuestionHelper extends QuestionHelper
{
  /**
   * The cursor.
   *
   * @var string $cursor The cursor.
   */
  protected string $cursor = '> ';
  /**
   * The cursor color.
   *
   * @var string $cursorColor The cursor color.
   */
  protected string $cursorColor = 'blue';
  /**
   * The message color.
   *
   * @var string $messageColor The message color.
   */
  protected string $messageColor = 'white';
  /**
   * Indentation for the message.
   *
   * @var int $indent The indentation.
   */
  protected int $indent = 2;
  /**
   * The selected index.
   *
   * @var int $selected The selected index.
   */
  protected int $selected = 0;

  /**
   * Asks the user the given question.
   *
   * @param resource $inputStream The input stream.
   * @param OutputInterface $output The output.
   * @param Question $question The question to ask.
   * @return mixed The user response.
   * @throws \RuntimeException In case the fallback is deactivated and the response cannot be hidden
   */
  private function doAsk($inputStream, OutputInterface $output, Question $question): mixed
  {
    if (! $question instanceof ChoiceQuestion)
    {
      return $this->doRegularAsk($inputStream, $output, $question);
    }

    $answer = null;
    $running = true;

    do {
      $this->handleInput();
      $this->update();
      $this->render($output, $question);
    } while($running);
    // While enter or Ctrl+C is not pressed

    // Write the prompt

    // Get the input stream

    // Render the choices

    return $answer;
  }

  /**
   * Asks the user the given question.
   *
   * @param resource $inputStream The input stream.
   * @param OutputInterface $output The output.
   * @param Question $question The question to ask.
   * @throws \RuntimeException In case the fallback is deactivated and the response cannot be hidden
   * @return mixed
   */
  private function doRegularAsk($inputStream, OutputInterface $output, Question $question): mixed
  {
    $this->writePrompt($output, $question);

    $autocomplete = $question->getAutocompleterCallback();

    if (null === $autocomplete || !self::$stty || !Terminal::hasSttyAvailable()) {
      $ret = false;
      if ($question->isHidden()) {
        try {
          $hiddenResponse = $this->getHiddenResponse($output, $inputStream, $question->isTrimmable());
          $ret = $question->isTrimmable() ? trim($hiddenResponse) : $hiddenResponse;
        } catch (RuntimeException $e) {
          if (!$question->isHiddenFallback()) {
            throw $e;
          }
        }
      }

      if (false === $ret) {
        $isBlocked = stream_get_meta_data($inputStream)['blocked'] ?? true;

        if (!$isBlocked) {
          stream_set_blocking($inputStream, true);
        }

        $ret = $this->readInput($inputStream, $question);

        if (!$isBlocked) {
          stream_set_blocking($inputStream, false);
        }

        if (false === $ret) {
          throw new MissingInputException('Aborted.');
        }
        if ($question->isTrimmable()) {
          $ret = trim($ret);
        }
      }
    } else {
      $autocomplete = $this->autocomplete($output, $question, $inputStream, $autocomplete);
      $ret = $question->isTrimmable() ? trim($autocomplete) : $autocomplete;
    }

    if ($output instanceof ConsoleSectionOutput) {
      $output->addContent(''); // add EOL to the question
      $output->addContent($ret);
    }

    $ret = \strlen($ret) > 0 ? $ret : $question->getDefault();

    if ($normalizer = $question->getNormalizer()) {
      return $normalizer($ret);
    }

    return $ret;
  }

  protected function writePrompt(OutputInterface $output, Question $question): void
  {
    $message = $question->getQuestion();

    if ($question instanceof ChoiceQuestion) {

    }
  }

  private function autocomplete()
  {

  }
}