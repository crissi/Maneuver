<?php namespace Fadion\Maneuver;

use Exception;
use League\Flysystem\Filesystem;

/**
 * Class Deploy
 *
 * Handles remote server operations.
 */
class Deploy {

    /**
     * @var \Fadion\Maneuver\Git
     */
    protected $git;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string Server credentials
     */
    protected $server;

    /**
     * @var string Revision filename
     */
    protected $revisionFile = '.revision';

    /**
     * @var string Commit to sync revision file to
     */
    protected $syncCommit;

    /**
     * @var bool
     */
    protected $isSubmodule = false;

    /**
     * @var array List of ignored files
     */
    protected $ignoredFiles;

    /**
     * @var array List of files to upload
     */
    protected $filesToUpload;

    /**
     * @var array List of files to delete
     */
    protected $filesToDelete;

    /**
     * Constructor
     *
     * @param \Fadion\Maneuver\Git $git
     * @param \League\Flysystem\Filesystem $filesystem
     * @param array $server
     */
    public function __construct(Git $git, Filesystem $filesystem, $server)
    {
        $this->git = $git;
        $this->filesystem = $filesystem;
        $this->ignoredFiles = $git->getIgnored();
        $this->server = $server;
    }

    /**
     * Compares local revision to the remote one and
     * builds files to upload and delete
     *
     * @throws Exception if unknown git diff status
     * @return string
     */
    public function compare()
    {
        $remoteRevision = null;
        $filesToUpload = array();
        $filesToDelete = array();

        // The revision file goes inside the submodule.
        if ($this->isSubmodule) {
            $this->revisionFile = $this->isSubmodule . '/' . $this->revisionFile;
        }

        if ($this->filesystem->has($this->revisionFile)) {
            $remoteRevision = $this->filesystem->read($this->revisionFile);

            $message = "\r\n» Taking it from '" . substr($remoteRevision, 0, 7) . "'";
        } else {
            $message = "\r\n» Fresh deployment - grab a coffee";
        }

        // A remote version exists.
        if ($remoteRevision) {
            // Get the files from the diff.
            $output = $this->git->diff($remoteRevision);

            foreach ($output as $line) {
                // Added, changed or modified.
                if ($line[0] == 'A' or $line[0] == 'C' or $line[0] == 'M') {
                    $filesToUpload[] = trim(substr($line, 1));
                }
                // Deleted.
                elseif ($line[0] == 'D') {
                    $filesToDelete[] = trim(substr($line, 1));
                }
                // Unknown status.
                else {
                    throw new Exception("Unknown git-diff status: {$line[0]}");
                }
            }
        }
        // No remote version. Get all files.
        else {
            $filesToUpload = $this->git->files();
        }

        // Remove ignored files from the list of uploads.
        $filesToUpload = array_diff($filesToUpload, $this->ignoredFiles);

        $this->filesToUpload = $filesToUpload;
        $this->filesToDelete = $filesToDelete;

        return $message;
    }

    /**
     * Getter for $this->filesToUpload
     *
     * @return array
     */
    public function getFilesToUpload()
    {
        return $this->filesToUpload;
    }

    /**
     * Getter for $this->filesToDelete
     *
     * @return array
     */
    public function getFilesToDelete()
    {
        return $this->filesToDelete;
    }

    /**
     * Getter for $this->isSubmodule
     *
     * @return mixed
     */
    public function getIsSubmodule()
    {
        return $this->isSubmodule;
    }

    /**
     * Setter for $this->isSubmodule
     *
     * @param string $value
     */
    public function setIsSubmodule($value)
    {
        $this->isSubmodule = $value;
    }

    /**
     * Sets the commit to sync revision
     * file to
     *
     * @param string $value
     */
    public function setSyncCommit($value)
    {
        $this->syncCommit = $value;
    }

    /**
     * Uploads file
     *
     * @param string $file
     * @return array
     */
    public function upload($file)
    {
        if ($this->isSubmodule) {
            $file = $this->isSubmodule.'/'.$file;
        }

        try {
            $dir = explode('/', dirname($file));
            $path = '';
            $output = array();

            // Skip basedir or parent.
            if ($dir[0] != '.' and $dir[0] != '..') {
                // Iterate through directory pieces.
                for ($i = 0, $count = count($dir); $i < $count; $i++) {
                    $path .= $dir[$i].'/';

                    // The directory doesn't exist.
                    if (! $this->filesystem->has($path)) {
                        // Attempt to create the directory.
                        $this->filesystem->createDir($path);
                        $output[] = "Created directoy '$path'.'";
                    }
                }
            }

            $uploaded = false;
            $attempts = 1;

            // Loop until $uploaded becomes a valid
            // resource.
            while (!$uploaded) {
                // Attempt to upload the file 10 times
                // and exit if it fails.
                if ($attempts == 10) {
                    $output[] = "Tried to upload $file 10 times, and failed 10 times. Something is wrong, so I'm going to stop executing now.";
                    return $output;
                }

                $data = file_get_contents($file);
                if ($this->filesystem->has($file)) {
                    $uploaded = $this->filesystem->update($file, $data);
                } else {
                    $uploaded = $this->filesystem->put($file, $data);
                }

                if (!$uploaded) {
                    $attempts++;
                }
            }

            $output[] = "√ \033[0;37m{$file}\033[0m \033[0;32muploaded\033[0m";

        } catch (\Exception $e) {
            $output[] = "⊘ \033[0;37mFailed\033[0m";
        } catch (\ErrorException $e) {
            $output[] = "⊘ \033[0;37mFailed\033[0m";
        }

        return $output;
    }

    /**
     * Delete file
     *
     * @param $file
     * @return string
     */
    public function delete($file)
    {
        $this->filesystem->delete($file);

        return "× \033[0;37m{$file}\033[0m \033[0;31mremoved\033[0m";
    }

    /**
     * Writes latest revision to the remote
     * revision file
     *
     * @throws Exception if can't update revision file
     */
    public function writeRevision()
    {
        if ($this->syncCommit) {
            $localRevision = $this->syncCommit;
        } else {
            $localRevision = $this->git->localRevision()[0];
        }

        try {
            $this->filesystem->put($this->revisionFile, $localRevision);
        }
        catch (Exception $e) {
            throw new Exception("Could not update the revision file on server: {$e->getMessage()}");
        }
    }

}