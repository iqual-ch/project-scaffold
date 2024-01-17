<?php

namespace iqual\Composer\ProjectScaffold\Util;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Merge contents from a source by replacing the variables in the destination.
 *
 * Supports line-separated files (e.g. `.gitignore`),
 * environment files (i.e. `.env`), JSON (i.e. `.json`)
 * and YAML (i.e. `.yml` and `.yaml`) files.
 *
 * @internal
 */
class ContentMerger {

  /**
   * Merge type for environment files.
   */
  const MERGE_TYPE_ENV = "env";

  /**
   * Merge type for line-by-line files.
   */
  const MERGE_TYPE_LINE = "line";

  /**
   * Merge type for JSON files.
   */
  const MERGE_TYPE_JSON = "json";

  /**
   * Merge type for YAML files.
   */
  const MERGE_TYPE_YAML = "yaml";

  /**
   * ContentMerger constructor.
   */
  public function __construct(array $default_data = []) {
  }

  /**
   * Detects the merge type from a file path.
   *
   * @param string $file_path
   *   Absolute path to the file.
   *
   * @return string
   *   The type of merge.
   */
  public static function detectMergeType(string $file_path) {
    $merge_type = NULL;
    // Detect .env files (including other suffixes, e.g. .env.secrets).
    if (str_contains($file_path, ".env")) {
      $merge_type = self::MERGE_TYPE_ENV;
    }
    // Detect line-separated files, e.g. .gitignore.
    elseif (preg_match('/^\.((git|docker)ignore|gitattributes)(\.twig)?$/', $file_path)) {
      $merge_type = self::MERGE_TYPE_LINE;
    }
    // Detect JSON files.
    elseif (str_contains($file_path, ".json")) {
      $merge_type = self::MERGE_TYPE_JSON;
    }
    // Detect YAML files.
    elseif (str_contains($file_path, ".yaml") || str_contains($file_path, ".yml")) {
      $merge_type = self::MERGE_TYPE_YAML;
    }
    // Unknown file type.
    else {
      throw new \RuntimeException("Could not merge file. Could not detect type of merge from file");
    }

    return $merge_type;
  }

  /**
   * Merges two pieces of content.
   *
   * @param string $original_contents
   *   The original destination file's contents.
   * @param string $merge_contents
   *   The contents of the source file to be merged into the original.
   * @param string $type
   *   The type of the merge operation.
   * @param bool $process_comments
   *   If comments in the content should be processed.
   *
   * @return string
   *   The merged content.
   */
  public static function mergeContents(string $original_contents, string $merge_contents, string $type = self::MERGE_TYPE_ENV, bool $process_comments = TRUE) {
    // If there is nothing to merge return the original.
    if (empty($merge_contents)) {
      return $original_contents;
    }

    $merge_contents_array = self::convertContentsToArray($merge_contents, $type);

    // Merge either two environment files, or line-separate files.
    if ($type == self::MERGE_TYPE_ENV || $type == self::MERGE_TYPE_LINE) {
      // Directly write merge.
      return self::convertMergeToContents($original_contents, $merge_contents_array, $type);
    }
    // Merge either JSON or YAML files.
    elseif ($type == self::MERGE_TYPE_JSON || $type == self::MERGE_TYPE_YAML) {
      // Merge arrays before writing.
      $original_contents_array = self::convertContentsToArray($original_contents, $type, $process_comments);
      $merged_contents_array = array_replace_recursive($original_contents_array, $merge_contents_array);

      // Only write merge if arrays are not equal.
      if (!self::arraysAreEqual($original_contents_array, $merged_contents_array)) {
        return self::convertMergeToContents($original_contents, $merged_contents_array, $type);
      }
      else {
        return $original_contents;
      }
    }

  }

  /**
   * Merges two pieces of content.
   *
   * @param string $contents
   *   The file's contents.
   * @param string $type
   *   The type of the merge operation.
   * @param bool $process_comments
   *   If comments in the content should be processed.
   *
   * @return array
   *   The converted contents.
   */
  protected static function convertContentsToArray(string $contents, string $type = "env", bool $process_comments = FALSE) {
    $contents_array = [];

    // Detect EOL of contents.
    $eol = self::detectContentEol($contents);

    // Convert .env files to array.
    if ($type == self::MERGE_TYPE_ENV) {
      $lines = explode($eol, trim($contents));
      foreach ($lines as $line) {
        // Ignore comments in source.
        if (preg_match('/^ +#/', $line) == FALSE && strpos($line, '=') !== FALSE) {
          $line_array = explode('=', $line);
          $contents_array[$line_array[0]] = $line_array[1];
        }
      }
    }
    // Convert "line"-files to array.
    elseif ($type == self::MERGE_TYPE_LINE) {
      $lines = explode($eol, trim($contents));
      foreach ($lines as $line) {
        // Ignore comments in source.
        if (strpos($line, '#') === FALSE) {
          $line_array = preg_split('/\s/', $line);
          $contents_array[$line_array[0]] = $line;
        }
      }
    }
    // Convert JSON files to array.
    elseif ($type == self::MERGE_TYPE_JSON) {
      $contents_array = @json_decode($contents, TRUE);
      if ($contents_array === NULL && json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException("Could not parse JSON file. (Error code: " . json_last_error() . ")");
      }
    }
    // Convert YAML to array.
    elseif ($type == self::MERGE_TYPE_YAML) {
      // Integrate comments and empty lines before conversion.
      if ($process_comments === TRUE) {
        $converted_contents = self::convertYamlComments($contents, $eol);
      }
      // Do not process comments.
      else {
        $converted_contents = $contents;
      }

      // Convert the processed YAML contents to an array.
      try {
        $contents_array = Yaml::parse($converted_contents, Yaml::PARSE_CUSTOM_TAGS);
      }
      catch (ParseException $exception) {
        throw new \RuntimeException("Could not parse YAML file. Error: " . $exception->getMessage());
      }
    }

    // Do not return NULL arrays, but empty ones.
    if ($contents_array === NULL) {
      $contents_array = [];
    }

    return $contents_array;
  }

  /**
   * Convert the merge array into contents.
   *
   * @param string $contents
   *   The original contents to merge into.
   * @param array $merged_contents_array
   *   The array of of the contents to be merged.
   * @param string $type
   *   The type of the merge operation.
   *
   * @return string
   *   The merged contents.
   */
  protected static function convertMergeToContents(string $contents, array $merged_contents_array, string $type = self::MERGE_TYPE_ENV) {
    $merged_contents = "";
    $new_contents = [];

    // Detect the EOL of the contents.
    $eol = self::detectContentEol($contents);

    // Write merges to .env files.
    if ($type == self::MERGE_TYPE_ENV) {
      // Get contents line-by-line and process in reverse order.
      $lines = explode($eol, $contents);
      $reversed_lines = array_reverse($lines);
      foreach ($reversed_lines as $line_number => $line) {
        // Ignore comments when comparing values.
        if (preg_match('/^ +#/', $line) == FALSE && strpos($line, '=') !== FALSE) {
          $line_array = explode('=', $line);

          // Only update changed values in a .env file.
          if (array_key_exists($line_array[0], $merged_contents_array)) {
            $reversed_lines[$line_number] = $line_array[0] . "=" . $merged_contents_array[$line_array[0]];
            unset($merged_contents_array[$line_array[0]]);
          }
        }
      }

      // Add new variables to the end of the file.
      if (count($merged_contents_array) > 0) {
        foreach ($merged_contents_array as $key => $value) {
          $new_contents[] = $key . "=" . $value;
        }
      }

      // Write file line-by-line and add new contents.
      $merged_contents = implode($eol, array_merge(array_reverse($reversed_lines), $new_contents));
    }
    // Write merges to "line"-type files.
    elseif ($type == self::MERGE_TYPE_LINE) {
      // Get contents line-by-line and process in reverse order.
      $lines = explode($eol, $contents);
      $reversed_lines = array_reverse($lines);
      foreach ($reversed_lines as $line_number => $line) {
        // Ignore comments when comparing values.
        if (strpos($line, '#') === FALSE) {
          $line_array = preg_split('/\s/', $line);

          // Only update changed values in a .env file.
          if (array_key_exists($line_array[0], $merged_contents_array)) {
            $reversed_lines[$line_number] = $merged_contents_array[$line_array[0]];
            unset($merged_contents_array[$line_array[0]]);
          }
        }
      }

      // Add new variables to the end of the file.
      if (count($merged_contents_array) > 0) {
        foreach ($merged_contents_array as $key => $value) {
          $new_contents[] = $value;
        }
      }

      // Write file line-by-line and add new contents.
      $merged_contents = implode($eol, array_merge(array_reverse($reversed_lines), $new_contents));
    }
    // Write merges to JSON files.
    elseif ($type == self::MERGE_TYPE_JSON) {
      // Encode the merged content.
      $merged_contents = json_encode($merged_contents_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

      // Change indentation if non-default detected in source.
      $indentation = self::detectIndentation($contents, $type);
      if ($indentation != 4) {
        $merged_contents = self::convertJsonIndentation($merged_contents, $indentation);
      }
    }
    // Write merges to YAML files.
    elseif ($type == self::MERGE_TYPE_YAML) {
      // Detect original's indentation (w/o comments).
      $indentation = self::detectIndentation($contents, $type);

      // Detect original's level before array inlinig.
      $level = self::detectYamlLevel($contents, $indentation);

      // Convert array to YAML.
      $yaml_contents = Yaml::dump($merged_contents_array, $level, $indentation, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

      if (preg_match('/^\s*COMMENT_PLACEHOLDER_\d+: \'(.*?)\'$/m', $yaml_contents)) {
        // Replace comment placeholders with it's actual contents.
        $merged_contents = preg_replace('/^\s*COMMENT_PLACEHOLDER_\d+: \'(.*?)\'$/m', "$1", $yaml_contents);

        // Remove escaped single-quotes in comments.
        $merged_contents = preg_replace('/^(\s*#.*?)\'\'(.+?)\'\'/m', "$1'$2'", $merged_contents);

        // Remove unncessary newline at the end of the file.
        if (substr($merged_contents, -2) === "\n\n") {
          $merged_contents = substr($merged_contents, 0, -1);
        }
      }
      else {
        $merged_contents = $yaml_contents;
      }
    }

    return $merged_contents;
  }

  /**
   * Convert YAML Comments into variables.
   *
   * @param string $contents
   *   YAML Content.
   * @param string $eol
   *   The EOL of the content.
   *
   * @return string
   *   The processed YAML content including comments as variables.
   */
  protected static function convertYamlComments(string $contents, string $eol = "\n") {
    // Detect original's indentation (w/o comments).
    $original_indent = self::detectIndentation($contents, self::MERGE_TYPE_YAML);

    // Detect original's level before array inlinig.
    $level = self::detectYamlLevel($contents, $original_indent);

    $max_indent = $original_indent * $level;

    // Process lines in YAML file.
    $lines = explode($eol, $contents);
    foreach ($lines as $line_number => $line) {
      // Detect comment or empty line.
      if (preg_match('/^( *)(#|$)/', $line, $match)) {
        if (count($match) > 1) {
          $indent = strlen($match[1]);
        }
        else {
          $indent = 0;
        }
        $value_type = "";

        // Look for next variable and it's indentation and type.
        $line_counter = $line_number;
        while ($line_counter < count($lines)) {
          // Next line is a comment or empty line. Skip.
          if (preg_match('/^( *)(#|$)/', $lines[$line_counter])) {
            $line_counter++;
          }
          // Next line is a variable, set indentation.
          else {
            if (preg_match('/^( *)(\- )?/', $lines[$line_counter], $match)) {
              if (count($match) > 1) {
                $indent = strlen($match[1]);
              }
              else {
                $indent = 0;
              }
              if (count($match) > 2 && $match[2] == "- ") {
                $value_type = "- ";
              }
            }
            break;
          }
        }

        if ($indent < $max_indent) {
          // If the indentation doesn't exceed the maximum create a
          // new YAML variable and replace comment line with generated string.
          $lines[$line_number] = str_repeat(' ', $indent) . $value_type . Yaml::dump(["COMMENT_PLACEHOLDER_" . $line_number => $line]);
        }
        else {
          // Delete comment since it would be inlined later (YAML level).
          $lines[$line_number] = "";
        }
      }
    }
    $converted_contents = implode($eol, $lines);

    return $converted_contents;
  }

  /**
   * Detects the indentation of content.
   *
   * @param string $contents
   *   The content to analyze.
   * @param string $type
   *   The type of the merge operation.
   *
   * @return int
   *   The content's indentation.
   */
  protected static function detectIndentation(string $contents, string $type) {
    $indent = 0;

    if ($type == self::MERGE_TYPE_JSON) {
      $indent = 4;
      if (preg_match('/^ +/m', $contents, $match)) {
        $indent = strlen($match[0]);
      }
    }
    elseif ($type == self::MERGE_TYPE_YAML) {
      $indent = 4;
      if (preg_match('/^ +[^# ]/m', $contents, $match)) {
        $indent = strlen($match[0]) - 1;
      }
    }

    return $indent;
  }

  /**
   * Change indentation of JSON contents.
   *
   * @param string $contents
   *   The JSON content to convert.
   * @param int $indent
   *   The indentation of the content.
   *
   * @return string
   *   The newly indented JSON content.
   */
  protected static function convertJsonIndentation(string $contents, int $indent) {
    return preg_replace_callback('/^ +/m', function ($match) use ($indent) {
      return str_repeat(' ', (strlen($match[0]) / 4) * $indent);
    }, $contents);
  }

  /**
   * Detects the the level before YAML content gets inlined.
   *
   * @param string $contents
   *   The content to analyze.
   * @param int $indent
   *   The indentation of the content.
   *
   * @return string
   *   The level.
   */
  protected static function detectYamlLevel(string $contents, int $indent) {
    $level = 2;
    if (preg_match('/^( +)[^#\'"\s]+? (\{|\[)/m', $contents, $match)) {
      if (strlen($match[1]) > 0) {
        $level = (strlen($match[1]) / $indent) + 1;
      }
      else {
        $level = 1;
      }
    }
    else {
      $level = 99;
    }

    return $level;
  }

  /**
   * Detect End of Line typy of content.
   *
   * @param string $contents
   *   Content.
   *
   * @return string
   *   The EOL.
   */
  protected static function detectContentEol(string $contents) {
    $eol = "\n";
    if (strpos($contents, "\r\n") !== FALSE) {
      $eol = "\r\n";
    }

    return $eol;
  }

  /**
   * Check if two arrays are equal recursively.
   *
   * @param array $a
   *   First array.
   * @param array $b
   *   Second array.
   *
   * @return bool
   *   If the arrays are equal.
   */
  protected static function arraysAreEqual(array $a, array $b) {
    // If the indexes don't match, return immediately.
    if (array_keys($a) != array_keys($b)) {
      return FALSE;
    }
    // Compare the values of the arrays.
    foreach ($a as $k => $v) {
      if (is_array($v)) {
        if (!self::arraysAreEqual($v, $b[$k])) {
          return FALSE;
        }
      }
      else {
        if ($v !== $b[$k]) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

}
