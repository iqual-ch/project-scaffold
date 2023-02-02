<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use Composer\IO\IOInterface;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldResult;
use iqual\Composer\ProjectScaffold\Util\Interpolator;

/**
 * Scaffold operation to skip a scaffold file (do nothing).
 *
 * @internal
 */
class ReadOperation extends AbstractOperation {

  /**
   * Identifies Skip operations.
   */
  const ID = 'read';

  /**
   * The relative path to the source file.
   *
   * @var \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath
   */
  public $source;

  /**
   * ReadOperation constructor.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $sourcePath
   *   The relative path to the source file.
   */
  public function __construct(ScaffoldFilePath $sourcePath) {
    $this->source = $sourcePath;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateContents(array $variables = []) {
    return json_decode(file_get_contents($this->source->fullPath()), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isTemplated() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options, ScaffoldOptions $package_options) {
    $package_name = $package_options->getPackageName();

    // Log initialization.
    $interpolator = $destination->getInterpolator();
    $this->source->addInterpolationData($interpolator);
    $io->write($interpolator->interpolate("  - Reading <info>[src-rel-path]</info>"), TRUE, 4);

    // Read the questions from the defined file.
    $scaffold_questions = $this->contents();

    // Get the root scaffold options.
    $scaffold_options = $options->getAllOptions();
    // Get the package scaffold options.
    $scaffold_package_options = $package_options->getAllOptions();

    // Resolve the package's scaffold options and the root options.
    $scaffold_variables = array_replace_recursive($scaffold_package_options, $scaffold_options);
    $scaffold_variable_keys = $this->getFlatScaffoldVariableKeys($scaffold_variables);

    // Add metadata to the interpolator.
    $metadata = [
      "root-package-name" => $options->getPackageName(),
      "package-name" => $package_name,
    ];
    $interpolator->addData($metadata);

    // Check if the current package set the options to be prompted.
    $prompt = $package_options->prompt();

    // If the package doesn't ask for a prompt
    // but there are missing requirements and
    // the IO is intearctive, prompt anyhow.
    if ($prompt === FALSE && $io->isInteractive() && array_key_exists("required", $scaffold_questions)) {
      if (array_diff($scaffold_questions["required"], $scaffold_variable_keys)) {
        $prompt = TRUE;
        $io->write("Did not find all required variables.", TRUE, 4);
      }
    }

    // If the user should be prompted and the package
    // has questions defined, ask them.
    if ($prompt && array_key_exists("questions", $scaffold_questions)) {
      $io->write("Configure <comment>{$package_name}</comment>:", TRUE, 2);

      foreach ($scaffold_questions["questions"] as $variable => $definition) {
        $value = "";
        // If a default was defined set the value to it.
        if (array_key_exists("default", $definition)) {
          $value = $interpolator->interpolate($definition["default"]);
        }
        // If value already exists in the scaffold variables set it to it.
        if (in_array($variable, $scaffold_variable_keys)) {
          $value = $interpolator->interpolate($this->getScaffoldVariable($variable, $scaffold_variables));
        }
        // If a filter is defined, filter the current value.
        if (array_key_exists("filter", $definition)) {
          $filter_array = preg_split('~(?<!\\\)\/~', $definition["filter"]);
          if (!empty($filter_array) && count($filter_array) == 4) {
            $pattern = $filter_array[1];
            $replacement = $filter_array[2];
            if ($pattern) {
              $value = preg_replace("/" . $pattern . "/", $replacement, $value);
            }
          }
        }
        // If a question is defined, prompt the question.
        if (array_key_exists("question", $definition) && $io->isInteractive()) {
          $value = $this->promptQuestions($io, $definition, $value);
        }

        // Set the new option variables.
        $this->setOptionVariable($variable, $value, $options, $interpolator, $scaffold_variables);
        // Update the list of keys.
        $scaffold_variable_keys = $this->getFlatScaffoldVariableKeys($scaffold_variables);
      }
    }

    // Check if there are missing required variables, if defined.
    if (array_key_exists("required", $scaffold_questions)) {
      if (!array_diff($scaffold_questions["required"], $scaffold_variable_keys)) {
        $io->write("All required variables found.", TRUE, $prompt ? 2 : 4);
      }
      else {
        throw new \Exception("Required values are missing.");
      }
    }

    return new ScaffoldResult($destination, FALSE);
  }

  /**
   * Get the scaffold variable keys in dot-notation.
   *
   * Only supports dot-notation two levels deep.
   *
   * @param array $scaffold_variables
   *   The current scaffold variables.
   *
   * @return array
   *   Array of scaffold variable keys in dot-notation.
   */
  protected function getFlatScaffoldVariableKeys(array $scaffold_variables) {
    $scaffold_variable_keys = [];

    foreach ($scaffold_variables as $variable_key => $scaffold_variable) {
      // If variable is an array process array.
      if (is_array($scaffold_variable)) {
        foreach ($scaffold_variable as $variable_subkey => $scaffold_subvariable) {
          // Only support nesting two-levels deep.
          if (!is_array($scaffold_subvariable)) {
            // Return dot-notation of nested key.
            if (is_string($variable_subkey)) {
              $scaffold_variable_keys[] = $variable_key . "." . $variable_subkey;
            }
            // Return parent key if it's a sequential array.
            else {
              $scaffold_variable_keys[] = $variable_key;
              break;
            }
          }
        }
      }
      else {
        // Return key of scaffold variable.
        $scaffold_variable_keys[] = $variable_key;
      }
    }

    return $scaffold_variable_keys;
  }

  /**
   * Get scaffold variable.
   *
   * @param string $variable
   *   The variable to set in the options.
   * @param array $scaffold_variables
   *   The current scaffold variables.
   *
   * @return string
   *   The value of the variable.
   */
  protected function getScaffoldVariable(string $variable, array $scaffold_variables) {
    // Check for dot-noation in variable.
    if (strpos($variable, ".") === FALSE) {
      if (array_key_exists($variable, $scaffold_variables) && !is_array($scaffold_variables[$variable])) {
        return $scaffold_variables[$variable];
      }
    }
    // Resolve dot-notation (e.g. `runtime.php_version`).
    else {
      $split_variable = explode('.', $variable);

      // Only support up to two levels.
      if (count($split_variable) == 2) {
        // Return nested data.
        if (array_key_exists($split_variable[0], $scaffold_variables)) {
          $data = $scaffold_variables[$split_variable[0]];
          if (array_key_exists($split_variable[1], $data) && !is_array($data[$split_variable[1]])) {
            return $data[$split_variable[1]];
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Set option variables.
   *
   * @param string $variable
   *   The variable to set in the options.
   * @param string $value
   *   The value to set the variable to.
   * @param \iqual\Composer\ProjectScaffold\Options\ScaffoldOptions $options
   *   The current scaffold options.
   * @param \iqual\Composer\ProjectScaffold\Util\Interpolator $interpolator
   *   The current Interpolator.
   * @param array $scaffold_variables
   *   The current scaffold variables.
   */
  protected function setOptionVariable(string $variable, string $value, ScaffoldOptions $options, Interpolator $interpolator, array &$scaffold_variables) {
    // Check for dot-noation in variable.
    if (strpos($variable, ".") === FALSE) {
      $options->setOption($variable, $value);
      $interpolator->addData([$variable => $value]);
      $scaffold_variables[$variable] = $value;
    }
    // Resolve dot-notation (e.g. `runtime.php_version`).
    else {
      $split_variable = explode('.', $variable);

      // Only support up to two levels.
      if (count($split_variable) == 2) {
        // Get current data and set the option value.
        $data = $options->getOption($split_variable[0]) ?: [];
        $data[$split_variable[1]] = $value;

        $options->setOption($split_variable[0], $data);
        $interpolator->addData([$split_variable[0] => $data]);

        // Update scaffold variables.
        if (!array_key_exists($split_variable[0], $scaffold_variables)) {
          $scaffold_variables[$split_variable[0]] = [];
        }
        $scaffold_variables[$split_variable[0]][$split_variable[1]] = $value;
      }
      else {
        throw new \Exception("Unsupported variable notation: {$variable}.");
      }
    }
  }

  /**
   * Prompt the user for the defined questions.
   *
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to write to.
   * @param array $definition
   *   Array of questions' file defintion.
   * @param mixed $default
   *   Default value when prompting.
   *
   * @return mixed
   *   Result of the user prompt.
   */
  protected function promptQuestions(IOInterface $io, array $definition = [], $default = "") {
    $default_text = \strval($default) ? " [<comment>" . \strval($default) . "</comment>]" : "";
    if (array_key_exists("options", $definition)) {
      $selection = $io->select($definition["question"] . $default_text . ":", $definition["options"], $default);
      return $definition["options"][$selection];
    }
    else {
      $validation = NULL;
      if (array_key_exists("validation", $definition)) {
        $validation = $definition["validation"];
      }
      $prompt_text = $definition["question"] . $default_text . ": ";
      return $io->askAndValidate($prompt_text, $this->questionValidation($validation), 10, $default);
    }
  }

  /**
   * Validate the inputs from the questions.
   *
   * @param mixed $pattern
   *   The pattern that should be validated (NULL or string).
   *
   * @return function
   *   Returns a validation function for a input according to the pattern.
   */
  protected function questionValidation($pattern) {
    return function ($input) use ($pattern) {
      if ($pattern !== NULL && !preg_match('/^' . $pattern . '$/', $input)) {
        throw new \InvalidArgumentException("The input does not match the required format: " . $pattern);
      }
      return $input;
    };
  }

}
