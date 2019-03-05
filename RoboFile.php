<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2019 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
require_once 'RoboFilePlugin.php';
class RoboFile extends RoboFilePlugin
{
   protected static $banned = [
         'dist',
         'vendor',
         '.git',
         '.gitignore',
         '.tx',
         '.settings',
         '.project',
         '.buildpath',
         'tools',
         'tests',
         'screenshot*.png',
         'RoboFile*.php',
         'plugin.xml',
         'phpunit.xml.*',
         '.travis.yml',
         'save.sql',
   ];

   protected function getProjectPath() {
      return __DIR__;
   }

   protected function getPluginName() {
      return basename($this->getProjectPath());
   }

   protected function getVersion() {
      $setupFile = $this->getProjectPath(). "/setup.php";
      $setupContent = file_get_contents($setupFile);
      $constantName = "PLUGIN_" . strtoupper($this->getPluginName()) . "_VERSION";
      $pattern = "#^define\('$constantName', '([^']*)'\);$#m";
      preg_match($pattern, $setupContent, $matches);
      if (isset($matches[1])) {
         return $matches[1];
      }
      throw new Exception("Could not determine version of the plugin");
   }

   protected function getIsRelease() {
      $currentRev = $this->getCurrentCommitHash();
      $setupContent = $this->getFileFromGit('setup.php', $currentRev);
      $constantName = "PLUGIN_" . strtoupper($this->getPluginName()) . "_IS_OFFICIAL_RELEASE";
      $pattern = "#^define\('$constantName', ([^\)]*)\);$#m";
      preg_match($pattern, $setupContent, $matches);
      if (isset($matches[1])) {
         return $matches[1];
      }
      throw new Exception("Could not determine release status of the plugin");
   }

   protected function getGLPIMinVersion() {
      $setupFile = $this->getProjectPath(). "/setup.php";
      $setupContent = file_get_contents($setupFile);
      $constantName = "PLUGIN_" . strtoupper($this->getPluginName()) . "_GLPI_MIN_VERSION";
      $pattern = "#^define\('$constantName', '([^']*)'\);$#m";
      preg_match($pattern, $setupContent, $matches);
      if (isset($matches[1])) {
         return $matches[1];
      }

      throw new Exception("Could not determine version of the plugin");
   }

   /**
    * Override to change the banned list
    * @return array
    */
   protected function getBannedFiles() {
      return static::$banned;
   }

   //Own plugin's robo stuff

   /**
    * Build an redistribuable archive
    *
    * @param string $release 'release' if the archive is a release
    */
   public function archiveBuild($release = 'release') {
      $release = strtolower($release);
      $version = $this->getVersion();

      if (!$this->isSemVer($version)) {
         throw new Exception("$version is not semver compliant. See http://semver.org/");
      }

      if ($release != 'release') {
         if ($this->getIsRelease() === 'true') {
            throw new Exception('The Official release constant must be false');
         }
      } else {
         if ($this->getIsRelease() !== 'true') {
            throw new Exception('The Official release constant must be true');
         }

         if ($this->tagExists($version)) {
            throw new Exception("The tag $version already exists");
         }

         // if (!$this->isTagMatchesCurrentCommit($version)) {
         //    throw new Exception("HEAD is not pointing to the tag of the version to build");
         // }

         $versionTag = $this->getVersionTagFromXML($version);
         if (!is_array($versionTag)) {
            throw new Exception("The version does not exists in the XML file");
         }
      }

      // update version in package.json
      $this->sourceUpdatePackageJson($version);

      $this->updateChangelog();

      $diff = $this->gitDiff(['package.json']);
      $diff = implode("\n", $diff);
      if ($diff != '') {
         $this->taskGitStack()
            ->stopOnFail()
            ->add('package.json')
            ->commit('docs: bump version in package.json')
            ->run();
      }

      $this->taskGitStack()
         ->stopOnFail()
         ->add('CHANGELOG.md')
         ->commit('docs(changelog): update changelog')
         ->run();

      // Update locales
      $this->localesGenerate();
      $this->taskGitStack()
         ->stopOnFail()
         ->add('locales/*')
         ->commit('docs(locales): update translations')
         ->run();

      $rev = 'HEAD';
      $pluginName = $this->getPluginName();
      $pluginPath = $this->getProjectPath();
      $archiveWorkdir = "$pluginPath/output/dist/archive_workdir";
      $archiveFile = "$pluginPath/output/dist/glpi-" . $this->getPluginName() . "-$version.tar.bz2";
      $this->taskDeleteDir($archiveWorkdir)->run();
      $this->taskDeleteDir($archiveFile)->run();
      mkdir($archiveWorkdir, 0777, true);
      $filesToArchive = implode(' ', $this->getFileToArchive($rev));

      // Extract from the repo all files we want to have in the redistribuable archive
      $this->_exec("git archive --prefix=$pluginName/ $rev $filesToArchive | tar x -C '$archiveWorkdir'");

      // Add composer dependencies
      $this->_exec("composer install --no-dev --working-dir='$archiveWorkdir/$pluginName'");

      // Create the final archive
      $this->taskPack($archiveFile)
         ->addDir("$pluginName", "$archiveWorkdir/$pluginName")
         ->run();
   }

   protected function getTrackedFiles($version) {
      $output = [];
      exec("git ls-tree -r '$version' --name-only", $output, $retCode);
      if ($retCode != '0') {
         throw new Exception("Unable to get tracked files");
      }
      return $output;
   }

   protected function getFileToArchive($version) {
      $filesToArchive = $this->getTrackedFiles($version);

      // prepare banned items for regex
      $patterns = [];
      foreach ($this->getBannedFiles() as $bannedItem) {
         $pattern = "#" . preg_quote("$bannedItem", "#") . "#";
         $pattern = str_replace("\\?", ".", $pattern);
         $pattern = str_replace("\\*", ".*", $pattern);
         $patterns[] = $pattern;
      }

      // remove banned files from the list
      foreach ($patterns as $pattern) {
         $filteredFiles = [];
         foreach ($filesToArchive as $file) {
            if (preg_match($pattern, $file) == 0) {
               //Include the tracked file
               $filteredFiles[] = $file;
            }
         }

         // Repeat filtering from result with next banned files pattern
         $filesToArchive = $filteredFiles;
      }

      return $filesToArchive;
   }

   protected function getAllTags() {
      exec("git tag -l", $output, $retCode);
      if ($retCode != '0') {
         // An error occured
         throw new Exception("Unable to get tags from the repository");
      }
      return $output;
   }

   protected function tagExists($version) {
      $tags = $this->getAllTags();
      return in_array($version, $tags);
   }

   /**
    * Check the version is made of numbers separated by dots
    *
    * Returns true if the version is well formed, false otherwise
    *
    * @param string $version
    * @return boolean
    */
   protected function isSemVer($version) {
      $semverPattern = '#\bv?(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[\da-z\-]+(?:\.[\da-z\-]+)*)?(?:\+[\da-z\-]+(?:\.[\da-z\-]+)*)?\b#i';
      if (preg_match($semverPattern, $version) !== 1) {
         return false;
      }

      return true;
   }

   protected function getPluginXMLDescription() {
      $pluginXML = 'plugin.xml';
      if (!is_file($pluginXML) || !is_readable($pluginXML)) {
         throw Exception("plugin.xml file not found");
      }

      $xml = simplexml_load_string(file_get_contents($pluginXML));
      if ($xml === false) {
         throw new Exception("$pluginXML is not valid XML");
      }
      $json = json_encode($xml);
      return json_decode($json, true);
   }

   protected function getVersionTagFromXML($versionToSearch) {
      $xml = $this->getPluginXMLDescription();
      foreach ($xml['versions']['version'] as $version) {
         if ($version['num'] == $versionToSearch) {
            // version found
            return $version;
         }
      }

      return null;
   }

   protected function getCurrentCommitHash() {
      exec('git rev-parse HEAD', $output, $retCode);
      if ($retCode != '0') {
         throw new Exception("failed to get curent commit hash");
      }
      return $output[0];
   }

   protected function isTagMatchesCurrentCommit($tag) {
      $commitHash = $this->getCurrentCommitHash();
      exec("git tag -l --contains $commitHash", $output, $retCode);
      if (isset($output[0]) && $output[0] == $tag) {
         return  true;
      }

      return false;
   }

   /**
    */
   protected function updateChangelog() {
       exec("node_modules/.bin/conventional-changelog -p angular -i CHANGELOG.md -s", $output, $retCode);
      if ($retCode > 0) {
         throw new Exception("Failed to update the changelog");
      }

       return true;
   }

   public function localesExtract() {
      $potfile = strtolower("glpi.pot");
      $phpSources = "*.php ajax/*.php front/*.php inc/*.php inc/fields/*.php install/*.php js/*.php";
      // extract locales from source code
      $command = "xgettext $phpSources -o locales/$potfile -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po";
      $command.= " --keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t --keyword=_ex:1c,2,3t";
      $command.= " --keyword=_sx:1c,2,3t --keyword=_nx:1c,2,3,5t";
      $this->_exec($command);
      return $this;
   }

   /**
    * Build MO files
    *
    * @return void
    */
   public function localesMo() {
      $localesPath = $this->getProjectPath() . '/locales';
      if ($handle = opendir($localesPath)) {
         while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
               $poFile = "$localesPath/$file";
               if (pathinfo($poFile, PATHINFO_EXTENSION) == 'po') {
                  $moFile = str_replace('.po', '.mo', $poFile);
                  $command = "msgfmt $poFile -o $moFile";
                  $this->_exec($command);
               }
            }
         }
         closedir($handle);
      }
      return $this;
   }

   /**
    *
    * @param string $filename
    * @param string $version
    */
   protected function updateJsonFile($filename, $version) {
       // get Package JSON
       $filename = __DIR__ . "/$filename";
       $jsonContent = file_get_contents($filename);
       $jsonContent = json_decode($jsonContent, true);

       // update version
      if (empty($version)) {
         echo "Version not found in setup.php\n";
         return;
      }
       $jsonContent['version'] = $version;
       file_put_contents($filename, json_encode($jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
   }

   /**
    * @param $version
    */
   protected function sourceUpdatePackageJson($version) {
       $this->updateJsonFile('package.json', $version);
   }

   /**
    * @param string $version
    */
   protected function sourceUpdateComposerJson($version) {
       $this->updateJsonFile('composer.json', $version);
   }

   /**
    * Update headers in source files
    */
   public function codeHeadersUpdate() {
      $toUpdate = $this->getTrackedFiles('HEAD');
      foreach ($toUpdate as $file) {
         $this->replaceSourceHeader($file);
      }
   }

   /**
    * Read the header template from a file
    * @throws Exception
    * @return string
    */
   protected function getHeaderTemplate() {
      if (empty($this->headerTemplate)) {
         $this->headerTemplate = file_get_contents(__DIR__ . '/tools/HEADER');
         if (empty($this->headerTemplate)) {
            throw new Exception('Header template file not found');
         }
      }

      $copyrightRegex = "#Copyright (\(c\)|©) (\d{4}\s*-\s*)(\d{4}) #iUm";
      $year = date("Y");
      $replacement = 'Copyright © ${2}' . $year . ' ';
      $this->headerTemplate = preg_replace($copyrightRegex, $replacement, $this->headerTemplate);

      return $this->headerTemplate;
   }

   /**
    * Format header template for a file type based on extension
    *
    * @param string $extension
    * @param string $template
    * @return string
    */
   protected function getFormatedHeaderTemplate($extension, $template) {
      switch ($extension) {
         case 'php':
         case 'css':
            $lines = explode("\n", $template);
            foreach ($lines as &$line) {
               $line = rtrim(" * $line");
            }
            return implode("\n", $lines);
            break;

         default:
            return $template;
      }
   }

   /**
    * Update source code header in a source file
    * @param string $filename
    */
   protected function replaceSourceHeader($filename) {
      $filename = __DIR__ . "/$filename";

      // define regex for the file type
      $ext = pathinfo($filename, PATHINFO_EXTENSION);
      switch ($ext) {
         case 'php':
            $prefix              = "\<\?php\\n/\*(\*)?\\n";
            $replacementPrefix   = "<?php\n/**\n";
            $suffix              = "\\n( )?\*/";
            $replacementSuffix   = "\n */";
            break;

         case 'css':
            $prefix              = "/\*(\*)?\\n";
            $replacementPrefix   = "/**\n";
            $suffix              = "\\n( )?\*/";
            $replacementSuffix   = "\n */";
            break;
         default:
            // Unhandled file format
            return;
      }

      // format header template for the file type
      $header = trim($this->getHeaderTemplate());
      $formatedHeader = $replacementPrefix . $this->getFormatedHeaderTemplate($ext, $header) . $replacementSuffix;

      // get the content of the file to update
      $source = file_get_contents($filename);

      // update authors in formated template
      $headerMatch = [];
      $originalAuthors = [];
      $authors = [];
      $authorsRegex = "#^.*(\@author .*)$#Um";
      preg_match('#^' . $prefix . '(.*)' . $suffix . '#Us', $source, $headerMatch);
      if (isset($headerMatch[0])) {
         $originalHeader = $headerMatch[0];
         preg_match_all($authorsRegex, $originalHeader, $originalAuthors);
         if (!is_array($originalAuthors)) {
            $originalAuthors = [$originalAuthors];
         }
         if (isset($originalAuthors[1])) {
            $originalAuthors[1] = array_unique($originalAuthors[1]);
            $originalAuthors = $this->getFormatedHeaderTemplate($ext, implode("\n", $originalAuthors[1]));
            $countOfAuthors = preg_match_all($authorsRegex, $formatedHeader);
            if ($countOfAuthors !== false) {
               // Empty all author lines except the last one
               $formatedHeader = preg_replace($authorsRegex, '', $formatedHeader, $countOfAuthors - 1);
               // remove the lines previously reduced to zero
               $lines = explode("\n", $formatedHeader);
               $formatedHeader = [];
               foreach ($lines as $line) {
                  if ($line !== '') {
                     $formatedHeader[] = $line;
                  };
               }
               $formatedHeader = implode("\n", $formatedHeader);
               $formatedHeader = preg_replace($authorsRegex, $originalAuthors, $formatedHeader, 1);
            }
         }
      }

      // replace the header if it exists
      $source = preg_replace('#^' . $prefix . '(.*)' . $suffix . '#Us', $formatedHeader, $source, 1);
      if (empty($source)) {
         throw new Exception("An error occurred while processing $filename");
      }

      file_put_contents($filename, $source);
   }

   /**
    * @param array $files files to commit
    * @param string $version1
    * @param string $version2
    */
   protected function gitDiff(array $files, $version1 = '', $version2 = '') {
      if (count($files) < 1) {
         $arg = '-u';
      } else {
         $arg = '"' . implode('" "', $files) . '"';
      }

      if ($version1 == '' && $version2 == '') {
         $fromTo = '';
      } else {
         $fromTo = "$version1..$version2";
      }

      exec("git diff $fromTo -- $arg", $output, $retCode);
      if ($retCode > 0) {
         throw new Exception("Failed to diff $fromTo");
      }

      return $output;
   }

   /**
    * Get a file from git tree
    * @param string $path
    * @param string $rev a commit hash, a tag or a branch
    * @throws Exception
    * @return string content of the file
    */
   protected function getFileFromGit($path, $rev) {
      $output = shell_exec("git show $rev:$path");
      if ($output === null) {
         throw new Exception ("coult not get file from git: $rev:$path");
      }
      return $output;
   }
}
