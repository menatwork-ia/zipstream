<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    zipstream
 * @license    GNU/LGPL
 * @filesource
 */

namespace ZipStream;

class ZipStream extends \Controller
{

    /**
     * Singleton pattern
     *
     * @var ZipStream
     */
    protected static $instance = null;

    /**
     * Zip handler.
     *
     * @var \ZipWriter
     */
    protected $objZip;

    /**
     * Contains the path to the zip archive.
     *
     * @var string
     */
    protected $strZipPath;

    /**
     * Contains the name of the zip archive.
     *
     * @var string
     */
    protected $strZipName;

    /**
     * Path for the temp folder.
     */
    const TEMPORARY_FOLDER = 'system/tmp';

    /**
     * Constructor
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Get instance of ZipStream
     *
     * @return ZipStream
     */
    public static function getInstance()
    {
        if (self::$instance == null)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Standardize a parameter (strip special characters and convert spaces)
     *
     * @param string  $strString            String to be standardize.
     *
     * @param boolean $blnPreserveUppercase Preserve uppercase
     *
     * @return string Return the standardize string.
     */
    protected function standardize($strString, $blnPreserveUppercase = false)
    {
        $arrSearch  = array('/[^a-zA-Z0-9 _-]+/', '/ +/', '/\-+/');
        $arrReplace = array('', '-', '-');

        $strString = html_entity_decode($strString, ENT_QUOTES, $GLOBALS['TL_CONFIG']['characterSet']);
        $strString = strip_insert_tags($strString);
        $strString = utf8_romanize($strString);
        $strString = preg_replace($arrSearch, $arrReplace, $strString);

        if (!$blnPreserveUppercase)
        {
            $strString = strtolower($strString);
        }

        return trim($strString, '-');
    }

    /**
     * @param  mixed $mixFiles   Single file or a list of files.
     *
     * @param string $strZipName Name for the zip file. [Optional]
     */
    public function zipDownload($mixFiles, $strZipName = null)
    {
        // Open a new zip archive.
        $this->openZipArchive($strZipName);
        $this->addFiles($mixFiles);
        $this->closeZipArchive();
        $this->outputZipArchive();
    }

    /**
     * Return html with a download link for a zip file.
     *
     * @param mixed  $linkId     The id for the module.
     *
     * @param array  $dataArray  List with all files for the zip.
     *
     * @param string $linkString Name of the download link.
     *
     * @param string $strZipName Name for the zip file. [Optional]
     *
     * @return string Return the html code.
     */
    public function zipStreamForm($linkId, $dataArray, $linkString = 'Download', $strZipName = null)
    {
        // Trigger the download.
        if (\Input::get('zipstream', true) == $linkId)
        {
            $this->zipDownload($dataArray, $strZipName);
        }

        // Build template with information.
        $objTemplate         = new \FrontendTemplate('zipstream');
        $objTemplate->link   = $linkString;
        $objTemplate->linkId = $linkId;
        $objTemplate->href   = \Environment::get('request') . (($GLOBALS['TL_CONFIG']['disableAlias'] || strpos(\Environment::get('request'), '?') !== false) ? '&amp;' : '?') . 'zipstream=' . $linkId;

        return $objTemplate->parse();
    }

    /**
     * Generate the filename and open a zip archive.
     *
     * @param string $strZipName Name of the zip file.
     */
    protected function openZipArchive($strZipName = null)
    {
        if (empty($strZipName))
        {
            $archiveFileName = 'download_' . rand(10000, 99999) . time() . '.zip';
        }
        else
        {
            $archiveFileName = sprintf("%s_%s.zip", $this->standardize($strZipName), time());
        }

        $this->objZip     = new \ZipWriter(self::TEMPORARY_FOLDER . DIRECTORY_SEPARATOR . $archiveFileName);
        $this->strZipPath = self::TEMPORARY_FOLDER . DIRECTORY_SEPARATOR . $archiveFileName;
        $this->strZipName = $archiveFileName;
    }

    /**
     * Close the zip archive.
     */
    protected function closeZipArchive()
    {
        $this->objZip->close();
    }

    /**
     * Add files to the zip archive.
     *
     * @param mixed $mixFiles Single file or a list of files.
     */
    protected function addFiles($mixFiles)
    {
        // Check if we have a array.
        if (!is_array($mixFiles))
        {
            $mixFiles = array($mixFiles);
        }

        foreach ($mixFiles as $strFile)
        {
            // Check if the file exists, if not go to the next one.
            if (!file_exists(TL_ROOT . DIRECTORY_SEPARATOR . $strFile) || !is_file(TL_ROOT . DIRECTORY_SEPARATOR . $strFile))
            {
                continue;
            }

            // Add each file of to the archive
            $this->objZip->addFile($strFile, basename($strFile));
        }
    }

    /**
     * Output the zip archive.
     */
    protected function outputZipArchive()
    {
        // Get the content from the zip archive.
        $objZipArchive = new \File($this->strZipPath);
        $strContent    = $objZipArchive->getContent();
        $objZipArchive->delete();
        $objZipArchive->close();

        // Create a new tmp file.
        $temp = tmpfile();
        fwrite($temp, $strContent);
        rewind($temp);

        // Clean the output buffer.
        ob_get_clean();

        // Write the http header.
        header('Content-Type: application/zip');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $this->strZipName . '"');
        header('Content-Length: ' . strlen($strContent));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        // Output the file.
        fpassthru($temp);

        // Close the file handler.
        fclose($temp);

        // HOOK: post download callback
        if (isset($GLOBALS['TL_HOOKS']['postDownload']) && is_array($GLOBALS['TL_HOOKS']['postDownload']))
        {
            foreach ($GLOBALS['TL_HOOKS']['postDownload'] as $callback)
            {
                $this->import($callback[0]);
                $this->$callback[0]->$callback[1]($this->strZipPath);
            }
        }

        exit;
    }
}