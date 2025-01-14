<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* This class handles all operations on files for the forum object.
*
* @author	Stefan Meyer <meyer@leifos.com>
* @version $Id$
*
* @ingroup ModulesForum
*/
class ilFileDataForum extends ilFileData
{
    /**
    * obj_id
    * @var integer obj_id of exercise object
    * @access private
    */
    public $obj_id;
    public $pos_id;

    /**
    * path of exercise directory
    * @var string path
    * @access private
    */
    public $forum_path;
    
    private $error;

    // fau: fastForumFiles - cache variable
    /**
     * Cache for file paths obj_id => paths[]
     * @var array
     */
    protected static $paths_cache = [];
    // fau.
    
    /**
    * Constructor
    * call base constructors
    * checks if directory is writable and sets the optional obj_id
    * @param integereger obj_id
    * @access	public
    */
    public function __construct($a_obj_id = 0, $a_pos_id = 0)
    {
        global $DIC;
        $this->error = $DIC['ilErr'];
        
        define('FORUM_PATH', 'forum');
        parent::__construct();
        $this->forum_path = parent::getPath() . "/" . FORUM_PATH;
        
        // IF DIRECTORY ISN'T CREATED CREATE IT
        if (!$this->__checkPath()) {
            $this->__initDirectory();
        }
        $this->obj_id = $a_obj_id;
        $this->pos_id = $a_pos_id;
    }

    public function getObjId()
    {
        return $this->obj_id;
    }
    public function getPosId()
    {
        return $this->pos_id;
    }
    public function setPosId($a_id)
    {
        $this->pos_id = $a_id;
    }
    
    /**
    * get forum path
    * @access	public
    * @return string path
    */
    public function getForumPath()
    {
        return $this->forum_path;
    }

    // fau: fastForumFiles - new function _getFilePathsOfForum()
    /**
     * Get the paths of files used by a forum
     *
     * @param string $a_forum_path
     * @param $a_obj_id
     * @return string[]
     */
    protected static function _getFilePathsOfForum($a_forum_path, $a_obj_id) {

        if (isset(self::$paths_cache[$a_obj_id])) {
            return self::$paths_cache[$a_obj_id];
        }

        // clear the cache if forum is changed
        // this should avoid overflows for delete operations
        self::$paths_cache = [];

        $paths = [];
        foreach (glob($a_forum_path . '/'. (int) $a_obj_id . '_*') as $path) {
            if (is_dir($path)) {
                continue;
            }
            $paths[] = $path;
        }

        self::$paths_cache[$a_obj_id] = $paths;
        return $paths;
    }
    // fau.

    /**
     * @return array
     */
    public function getFiles()
    {
        $directory_iterator = new DirectoryIterator($this->forum_path);
        $filter_iterator = new RegexIterator($directory_iterator, "/^{$this->obj_id}_(.+)$/");

        $files = [];
        foreach ($filter_iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            list($obj_id, $rest) = explode('_', $file->getFilename(), 2);
            if ($obj_id == $this->obj_id) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'md5' => md5($this->obj_id . '_' . $rest),
                    'name' => $rest,
                    'size' => $file->getSize(),
                    'ctime' => date('Y-m-d H:i:s', $file->getCTime())
                ];
            }
        }

        return $files;
    }

    /**
     * @return array
     */
    public function getFilesOfPost()
    {  
        $files = array();

        // fau: fastForumFiles - use the cached file path list
        foreach (self::_getFilePathsOfForum($this->forum_path, $this->obj_id) as $path) {

            list($obj_id, $pos_id, $rest) = explode('_', basename($path), 3);

            if ($obj_id == $this->obj_id && $pos_id == $this->getPosId()) {
                $stat = stat($path);
                $files[$rest] = array(
                    'path' => $path,
                    'md5' => md5($this->obj_id . '_' . $this->pos_id . '_' . $rest),
                    'name' => $rest,
                    'size' => $stat['size'],
                    'ctime' => date('Y-m-d H:i:s', $stat['ctime'])
                );
            }
        }
        // fau.
        return $files;
    }

    /**
     * @param int $a_new_frm_id
     * @return bool
     */
    public function moveFilesOfPost($a_new_frm_id = 0)
    {
        if ($a_new_frm_id) {
            $directory_iterator = new DirectoryIterator($this->forum_path);
            $filter_iterator = new RegexIterator($directory_iterator, "/^{$this->obj_id}_(\d+)_(.+)$/");

            foreach ($filter_iterator as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                [$obj_id, $pos_id, $rest] = explode('_', $file->getFilename(), 3);
                if ((int) $obj_id !== (int) $this->obj_id || (int) $pos_id !== (int) $this->getPosId()) {
                    continue;
                }

                ilFileUtils::rename(
                    $file->getPathname(),
                    $this->forum_path . '/' . $a_new_frm_id . '_' . $this->pos_id . '_' . $rest
                );
            }

            return true;
        }

        return false;
    }

    public function ilClone($a_new_obj_id, $a_new_pos_id)
    {
        foreach ($this->getFilesOfPost() as $file) {
            @copy(
                $this->getForumPath() . "/" . $this->obj_id . "_" . $this->pos_id . "_" . $file["name"],
                $this->getForumPath() . "/" . $a_new_obj_id . "_" . $a_new_pos_id . "_" . $file["name"]
            );
        }
        return true;
    }
    public function delete()
    {
        foreach ($this->getFiles() as $file) {
            if (file_exists($this->getForumPath() . "/" . $this->getObjId() . "_" . $file["name"])) {
                unlink($this->getForumPath() . "/" . $this->getObjId() . "_" . $file["name"]);
            }
        }
        return true;
    }

    /**
     *
     * Store uploaded files in filesystem
     *
     * @param	array	$files	Copy of $_FILES array,
     * @access	public
     * @return	bool
     *
     */
    public function storeUploadedFile($files)
    {
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $index => $name) {
                // remove trailing '/'
                $name = rtrim($name, '/');

                $filename = ilUtil::_sanitizeFilemame($name);
                $temp_name = $files['tmp_name'][$index];
                $error = $files['error'][$index];
                
                if (strlen($filename) && strlen($temp_name) && $error == 0) {
                    $path = $this->getForumPath() . '/' . $this->obj_id . '_' . $this->pos_id . '_' . $filename;
                    
                    $this->__rotateFiles($path);
                    ilUtil::moveUploadedFile($temp_name, $filename, $path);
                }
            }
            
            return true;
        } elseif (isset($files['name']) && is_string($files['name'])) {
            // remove trailing '/'
            $files['name'] = rtrim($files['name'], '/');
                
            $filename = ilUtil::_sanitizeFilemame($files['name']);
            $temp_name = $files['tmp_name'];
            
            $path = $this->getForumPath() . '/' . $this->obj_id . '_' . $this->pos_id . '_' . $filename;
            
            $this->__rotateFiles($path);
            ilUtil::moveUploadedFile($temp_name, $filename, $path);
            
            return true;
        }
        
        return false;
    }
    /**
    * unlink files: expects an array of filenames e.g. array('foo','bar')
    * @param array filenames to delete
    * @access	public
    * @return string error message with filename that couldn't be deleted
    */
    public function unlinkFiles($a_filenames)
    {
        if (is_array($a_filenames)) {
            foreach ($a_filenames as $file) {
                if (!$this->unlinkFile($file)) {
                    return $file;
                }
            }
        }
        return '';
    }
    /**
    * unlink one uploaded file expects a filename e.g 'foo'
    * @param string filename to delete
    * @access	public
    * @return bool
    */
    public function unlinkFile($a_filename)
    {
        if (file_exists($this->forum_path . '/' . $this->obj_id . '_' . $this->pos_id . '_' . $a_filename)) {
            return unlink($this->forum_path . '/' . $this->obj_id . '_' . $this->pos_id . "_" . $a_filename);
        }
    }
    /**
    * get absolute path of filename
    * @param string relative path
    * @access	public
    * @return string absolute path
    */
    public function getAbsolutePath($a_path)
    {
        return $this->forum_path . '/' . $this->obj_id . '_' . $this->pos_id . "_" . $a_path;
    }
    
    /**
    * get file data of a specific attachment
    * @param string md5 encrypted filename
    * @access public
    * @return array filedata
    */
    public function getFileDataByMD5Filename($a_md5_filename)
    {
        $files = ilUtil::getDir($this->forum_path);
        foreach ((array) $files as $file) {
            if ($file['type'] == 'file' && md5($file['entry']) == $a_md5_filename) {
                return array(
                    'path' => $this->forum_path . '/' . $file['entry'],
                    'filename' => $file['entry'],
                    'clean_filename' => str_replace($this->obj_id . '_' . $this->pos_id . '_', '', $file['entry'])
                );
            }
        }
        
        return false;
    }
    
    /**
    * get file data of a specific attachment
    * @param string|array md5 encrypted filename or array of multiple md5 encrypted files
    * @access public
    * @return boolean status
    */
    public function unlinkFilesByMD5Filenames($a_md5_filename)
    {
        $files = ilUtil::getDir($this->forum_path);
        if (is_array($a_md5_filename)) {
            foreach ((array) $files as $file) {
                if ($file['type'] == 'file' && in_array(md5($file['entry']), $a_md5_filename)) {
                    unlink($this->forum_path . '/' . $file['entry']);
                }
            }
            
            return true;
        } else {
            foreach ((array) $files as $file) {
                if ($file['type'] == 'file' && md5($file['entry']) == $a_md5_filename) {
                    return unlink($this->forum_path . '/' . $file['entry']);
                }
            }
        }
        
        return false;
    }

    /**
    * check if files exist
    * @param array filenames to check
    * @access	public
    * @return bool
    */
    public function checkFilesExist($a_files)
    {
        if ($a_files) {
            foreach ($a_files as $file) {
                if (!file_exists($this->forum_path . '/' . $this->obj_id . '_' . $this->pos_id . '_' . $file)) {
                    return false;
                }
            }
            return true;
        }
        return true;
    }

    // PRIVATE METHODS
    public function __checkPath()
    {
        if (!@file_exists($this->getForumPath())) {
            return false;
        }
        $this->__checkReadWrite();

        return true;
    }
    /**
    * check if directory is writable
    * overwritten method from base class
    * @access	private
    * @return bool
    */
    public function __checkReadWrite()
    {
        if (is_writable($this->forum_path) && is_readable($this->forum_path)) {
            return true;
        } else {
            $this->error->raiseError("Forum directory is not readable/writable by webserver", $this->error->FATAL);
        }
    }
    /**
    * init directory
    * overwritten method
    * @access	public
    * @return string path
    */
    public function __initDirectory()
    {
        if (is_writable($this->getPath())) {
            if (mkdir($this->getPath() . '/' . FORUM_PATH)) {
                if (chmod($this->getPath() . '/' . FORUM_PATH, 0755)) {
                    $this->forum_path = $this->getPath() . '/' . FORUM_PATH;
                    return true;
                }
            }
        }
        return false;
    }
    /**
    * rotate files with same name
    * recursive method
    * @param string filename
    * @access	private
    * @return bool
    */
    public function __rotateFiles($a_path)
    {
        if (file_exists($a_path)) {
            $this->__rotateFiles($a_path . ".old");
            return \ilFileUtils::rename($a_path, $a_path . '.old');
        }
        return true;
    }

    /**
     * @param $file
     * @return bool|void
     */
    public function deliverFile($file)
    {
        if (!$path = $this->getFileDataByMD5Filename($file)) {
            return ilUtil::sendFailure($this->lng->txt('error_reading_file'), true);
        } else {
            return ilUtil::deliverFile($path['path'], $path['clean_filename']);
        }
    }

    /**
     *
     */
    public function deliverZipFile()
    {
        global $DIC;
        
        $zip_file = $this->createZipFile();
        if (!$zip_file) {
            ilUtil::sendFailure($DIC->language()->txt('error_reading_file'), true);
            return false;
        } else {
            $post = new ilForumPost($this->getPosId());
            ilUtil::deliverFile($zip_file, $post->getSubject() . '.zip', '', false, true, false);
            ilUtil::delDir($this->getForumPath() . '/zip/' . $this->getObjId() . '_' . $this->getPosId());
            $DIC->http()->close();
        }
    }

    /**
     * @return null|string
     */
    protected function createZipFile()
    {
        $filesOfPost = $this->getFilesOfPost();
        ksort($filesOfPost);

        ilUtil::makeDirParents($this->getForumPath() . '/zip/' . $this->getObjId() . '_' . $this->getPosId());
        $tmp_dir = $this->getForumPath() . '/zip/' . $this->getObjId() . '_' . $this->getPosId();
        foreach ($filesOfPost as $file) {
            @copy($file['path'], $tmp_dir . '/' . $file['name']);
        }

        $zip_file = null;
        if (ilUtil::zip($tmp_dir, $this->getForumPath() . '/zip/' . $this->getObjId() . '_' . $this->getPosId() . '.zip')) {
            $zip_file = $this->getForumPath() . '/zip/' . $this->getObjId() . '_' . $this->getPosId() . '.zip';
        }

        return $zip_file;
    }
}
