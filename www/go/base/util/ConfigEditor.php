<?php


namespace GO\Base\Util;


class ConfigEditor {

	public static function save(\GO\Base\Fs\File $file, array $config) {
		$configData = "<?php\n";
		foreach ($config as $key => $value) {
//			if ($value === true) {
//				$configData .= '$config["' . $key . '"]=true;' . "\n";
//			} elseif ($value === false) {
//				$configData .= '$config["' . $key . '"]=false;' . "\n";
//			} else if(is_array($value)) {
				$configData .= '$config["' . $key . '"]=' . var_export($value,true).';' . "\n";
//			}else{
//				$configData .= '$config["' . $key . '"]="' . $value . '";' . "\n";
//			}
		}
		
		//make sure directory exists
		$file->parent()->create();

		return file_put_contents($file->path(), $configData);
	}
}