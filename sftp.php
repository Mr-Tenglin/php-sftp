<?php
namespace Tenglin\System;

use phpseclib\Net\SFTP as SecFtp;

class Sftp {
	private $sftp = false;
	private $DIRS = "linux"; // Sftp Server system version

	// new Sftp Login to SFTP server
	public function __construct($server = "", $user = "", $password = "", $port = 22) {
		if (!class_exists("\phpseclib\Net\SFTP")) {
			echo "Please use Composer to install phpseclib, Composer: composer require phpseclib/phpseclib:~2.0";
			exit();
		}
		if (is_array($server)) {
			extract($server);
		}
		if (!empty($server) && !empty($user) && !empty($password)) {
			$this->login($server, $user, $password, $port);
		}
	}

	// Function Login to SFTP server
	public function login($server, $user = "", $password = "", $port = 22) {
		if (is_array($server)) {
			extract($server);
		}
		try {
			$this->sftp = new SecFtp($server, $port);
			if (!$this->sftp->login($user, $password)) {
				$this->sftp = false;
			}
		} catch (Exception $e) {
			error_log("sftp login: " . $e->getMessage());
		}
		return $this->sftp;
	}

	// Test SFTP connection
	public function test() {
		$result = false;
		if ($this->sftp !== false) {
			$result = true;
		}
		return $result;
	}

	// Check if a file exists on SFTP Server
	public function is_file($remote_file) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->sftp->is_file($remote_file)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp is file: " . $e->getMessage());
		}
		return $result;
	}

	// Recursively deletes files and folder in given directory
	public function rmdir($remote_path) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->clean_dir($remote_path, $this->sftp)) {
					if (!$this->ends_with($remote_path, "/")) {
						if ($this->sftp->rmdir($remote_path)) {
							$result = true;
						}
					} else {
						$result = true;
					}
				}
			}
		} catch (Exception $e) {
			error_log("sftp rmdir: " . $e->getMessage());
		}
		return $result;
	}

	// Delete a file on remote SFTP server
	public function delete($remote_file) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->sftp->is_file($remote_file)) {
					if ($this->sftp->delete($remote_file)) {
						$result = true;
					}
				}
			}
		} catch (Exception $e) {
			error_log("sftp delete: " . $e->getMessage());
		}
		return $result;
	}

	// Recursively copy files and folders on remote SFTP server
	public function upload_dir($local_path, $remote_path) {
		$result = false;
		try {
			$remote_path = rtrim($remote_path, $this->dirs());
			if ($this->sftp !== false) {
				if (!$this->ends_with($local_path, "/")) {
					$remote_path = $remote_path . $this->dirs() . basename($local_path);
					$this->sftp->mkdir($remote_path);
				}
				if ($this->sftp->is_dir($remote_path)) {
					$result = $this->upload_all($this->sftp, $local_path, $remote_path);
				}
			}
		} catch (Exception $e) {
			error_log("sftp upload dir: " . $e->getMessage());
		}
		return $result;
	}

	// Download a file from remote SFTP server
	public function download($remote_file, $local_file) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->sftp->get($remote_file, $local_file)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp download: " . $e->getMessage());
		}
		return $result;
	}

	// Download a directory from remote SFTP server
	public function download_dir($remote_dir, $local_dir) {
		$result = false;
		try {
			if (is_dir($local_dir) && is_writable($local_dir)) {
				if ($this->sftp !== false) {
					if (!$this->ends_with($remote_dir, "/")) {
						$local_dir = rtrim($local_dir, $this->dirs()) . $this->dirs() . basename($remote_dir);
						mkdir($local_dir);
					}
					$local_dir = rtrim($local_dir, $this->dirs());
					$result = $this->download_all($this->sftp, $remote_dir, $local_dir);
				}
			} else {
				throw new Exception("Local directory does not exist or is not writable", 1);
			}
		} catch (Exception $e) {
			error_log("sftp download dir: " . $e->getMessage());
		}
		return $result;
	}

	// Rename a file on remote SFTP server
	public function rename($current_filename, $new_filename) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->sftp->rename($current_filename, $new_filename)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp rename: " . $e->getMessage());
		}
		return $result;
	}

	// Create a directory on remote SFTP server
	public function mkdir($directory) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->sftp->mkdir($directory, true)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp mkdir: " . $e->getMessage());
		}
		return $result;
	}

	// Create and fill in a file on remote SFTP server
	public function touch($remote_file, $content = "") {
		$result = false;
		try {
			if ($this->sftp !== false) {
				$local_file = tmpfile();
				fwrite($local_file, $content);
				fseek($local_file, 0);
				if ($this->sftp->put($remote_file, $local_file, SecFtp::SOURCE_LOCAL_FILE)) {
					$result = true;
				}
				fclose($local_file);
			}
		} catch (Exception $e) {
			error_log("sftp touch: " . $e->getMessage());
		}
		return $result;
	}

	// Upload a file on SFTP server
	public function upload($local_file, $remote_file) {
		$result = false;
		try {
			if ($this->sftp !== false) {
				if ($this->sftp->put($remote_file, $local_file, SecFtp::SOURCE_LOCAL_FILE)) {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp upload: " . $e->getMessage());
		}
		return $result;
	}

	// List files in given directory on SFTP server
	public function scandir($path) {
		$result = false;
		if ($this->sftp !== false) {
			$result = $this->sftp->nlist($path);
		}
		if (is_array($result)) {
			$result = array_diff($result, [".", ".."]);
		}
		return $result;
	}

	// Get default login SFTP directory aka pwd
	public function pwd() {
		$result = false;
		if ($this->sftp !== false) {
			$result = $this->sftp->pwd();
		}
		return $result;
	}

	// Recursively deletes files and folder
	private function clean_dir($remote_path, $sftp) {
		$result = false;
		$to_delete = 0;
		$deleted = 0;
		$list = $sftp->nlist($remote_path);
		foreach ($list as $element) {
			if ($element !== "." && $element !== "..") {
				$to_delete++;
				if ($sftp->is_dir($remote_path . $this->dirs() . $element)) {
					$this->clean_dir($remote_path . $this->dirs() . $element, $sftp);
					if ($sftp->rmdir($remote_path . $this->dirs() . $element)) {
						$deleted++;
					}
				} else {
					if ($sftp->delete($remote_path . $this->dirs() . $element)) {
						$deleted++;
					}
				}
			}
		}
		if ($deleted === $to_delete) {
			$result = true;
		}
		return $result;
	}

	// Recursively copy files and folders on remote SFTP server
	private function upload_all($sftp, $local_dir, $remote_dir) {
		$result = false;
		try {
			if (!$sftp->is_dir($remote_dir)) {
				if (!$sftp->mkdir($remote_dir)) {
					throw new Exception("Cannot create remote directory.", 1);
				}
			}
			$to_upload = 0;
			$uploaded = 0;
			$d = dir($local_dir);
			while ($file = $d->read()) {
				if ($file != "." && $file != "..") {
					$to_upload++;
					if (is_dir($local_dir . $this->dirs() . $file)) {
						if ($this->upload_all($sftp, $local_dir . $this->dirs() . $file, $remote_dir . $this->dirs() . $file)) {
							$uploaded++;
						}
					} else {
						if ($sftp->put($remote_dir . $this->dirs() . $file, $local_dir . $this->dirs() . $file, SecFtp::SOURCE_LOCAL_FILE)) {
							$uploaded++;
						}
					}
				}
			}
			$d->close();
			if ($to_upload === $uploaded) {
				$result = true;
			}
		} catch (Exception $e) {
			error_log("sftp upload all: " . $e->getMessage());
		}
		return $result;
	}

	// Recursive function to download remote files
	private static function download_all($sftp, $remote_dir, $local_dir) {
		$result = false;
		try {
			if ($sftp->is_dir($remote_dir)) {
				$files = $sftp->nlist($remote_dir);
				if ($files !== false) {
					$to_download = 0;
					$downloaded = 0;
					foreach ($files as $file) {
						if ($file != "." && $file != "..") {
							$to_download++;
							if ($sftp->is_dir($remote_dir . $this->dirs() . $file)) {
								mkdir($local_dir . $this->dirs() . basename($file));
								if ($this->download_all($sftp, $remote_dir . $this->dirs() . $file, $local_dir . $this->dirs() . basename($file))) {
									$downloaded++;
								}
							} else {
								if ($sftp->get($remote_dir . $this->dirs() . $file, $local_dir . $this->dirs() . basename($file))) {
									$downloaded++;
								}
							}
						}
					}
					if ($to_download === $downloaded) {
						$result = true;
					}
				} else {
					$result = true;
				}
			}
		} catch (Exception $e) {
			error_log("sftp download all: " . $e->getMessage());
		}
		return $result;
	}

	// Checks whether a string ends with given chars
	private function ends_with($haystack, $needle) {
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}

	private function dirs() {
		if (in_array($this->DIRS, ["Windows", "windows", "Win", "win"])) {
			return DIRECTORY_SEPARATOR;
		} else {
			return "/";
		}
	}
}
