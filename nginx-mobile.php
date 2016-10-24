<?php
/**
 * Nginx-Mobile
 *
 * Mobile regex generator for Nginx
 *
 * By Maxime Jobin 
 *
 * All rights reserved - 2016
 */


namespace MaximeJobin\NginxMobile;


class MobileConfiguration{

	protected $mobileDetectionUrl = 'https://raw.githubusercontent.com/serbanghita/Mobile-Detect/master/Mobile_Detect.json';

	protected $mobileDetectionData = null;

	protected $unserializedData = null;

	protected $outputFile = 'nginx-mobile.conf';

	protected $fh = null;


	/**
	 * Constructor
	 */
	public function __construct() 
	{
	}


	/**
	 * Get remote data and 
	 */
	public function setup()
	{
		// Get remote data
		$this->mobileDetectionData = file_get_contents($this->mobileDetectionUrl);

		// File validation
		if ($this->mobileDetectionData === false || empty($this->mobileDetectionData)) {
			throw new \Exception('Mobile detection file reading failed.');
		}

		// Get data in an array
		$this->unserializedData = json_decode($this->mobileDetectionData, true);

		// Array validation
		if ($this->unserializedData == null) {
			throw new \Exception('Mobile detection file could not be decoded. Invalid JSON format.');
		}
	}


	/**
	 * Generate the mobile configuration validation for Nginx
	 */
	public function generate() 
	{
		$this->setup();

		// Clear output file
		$this->openOutputFile();

		// Write file header
		$this->writeGeneratorHeader();

		$this->writeDefaultValues();
		$this->findRealUserAgent();

		$this->startMapMobile();
		$this->writeUserAgent('phones');
		$this->writeUserAgent('browsers');
		$this->writeUserAgent('os');
		$this->endMap();

		$this->startMapTablet();
		$this->writeUserAgent('tablets');
		$this->endMap();

		$this->cloudfrontValidation();

		$this->tabletToMobile();

		fclose($this->fh);

		print $this->unserializedData['version'];
	}


	/**
	 * Create a file pointer to write the configuration
	 */
	public function openOutputFile()
	{
		$this->fh = fopen($this->outputFile, 'w');

		if ($this->fh === false)
		{
			throw new \Exception('The configuration file could not be created.');
		}
	}


	/**
	 * Write the file header
	 */
	public function writeGeneratorHeader()
	{
		fwrite($this->fh, "####################################################################################################\n");
		fwrite($this->fh, "# Nginx-Mobile\n#\n# Mobile detection directives based on version {$this->unserializedData['version']}\n");
		fwrite($this->fh, "# Author: Maxime Jobin \n");
		fwrite($this->fh, "# URL: https://github.com/maximejobin/nginx-mobile \n#\n");
		fwrite($this->fh, "# Generated on: ". date("Y-m-d") ."\n");
		fwrite($this->fh, "####################################################################################################\n\n");
	}


	/**
	 * Write variables used in the script
	 */
	public function writeDefaultValues()
	{
		//fwrite($this->fh, "set \$is_mobile 0;\nset \$is_tmp_mobile 0;\nset \$is_tablet 0;\nset \$real_user_agent \"\";\n\n\n");
	}


	/**
	 * Check HTTP header to find the real user agent value
	 */
	public function findRealUserAgent()
	{
		/*
		if (isset($this->unserializedData['uaHttpHeaders']))
		{
			fwrite($this->fh, "# Find the real user agent\n");
			foreach($this->unserializedData['uaHttpHeaders'] as $header)
			{
				$lowerHeader = strtolower($header);
				fwrite($this->fh, 'map $' . $lowerHeader . ' $real_user_agent {' . "\n");
				fwrite($this->fh, " default \"\$real_user_agent\";\n");
				fwrite($this->fh, "}\n\n");
			}
			fwrite($this->fh, "\n");
		}
		*/
	}


	/**
	 * Start the mobile user agent mapping
	 */
	public function startMapMobile()
	{
		fwrite($this->fh, "map \$real_user_agent \$is_mobile {\n default 0;\n");	
	}


	/**
	 * Write the user agent regex according to the type (phones or tablets)
	 *
	 * @param string $type Device type: "phones" or "tablets"
	 *
	 */
	public function writeUserAgent($type)
	{
		if (isset($this->unserializedData['uaMatch'][$type]))
		{
			fwrite($this->fh, "\n # {$type}\n");
			foreach($this->unserializedData['uaMatch'][$type] as $device => $regex)
			{
				$alteredRegex = str_replace(' ', '\s', $regex);
				fwrite($this->fh, " ~*({$alteredRegex}) 1; #{$device}\n");
			}
		}
	}


	/**
	 * End mapping
	 */
	public function endMap()
	{
		fwrite($this->fh, "}\n\n");
	}


	/**
	 * Start the tablets user agent mapping
	 */
	public function startMapTablet()
	{
		fwrite($this->fh, "map \$real_user_agent \$is_tablet {\n default 0;\n");
	}


	/**
	 * If the tablet validation says it's a tablet, it's also a mobile
	 */
	public function tabletToMobile()
	{
		#fwrite($this->fh, "# If it's a tablet, it's also a mobile\n");
		#fwrite($this->fh, "if (\$is_tablet = 1) { set \$is_mobile 1; }\n\n");
	}

	public function cloudfrontValidation()
	{
		fwrite($this->fh, "# Cloudfront validation\n");
		fwrite($this->fh, "map \$http_cloudfront_is_mobile_viewer \$is_tablet {\n");
		fwrite($this->fh, "}\n\n");
		fwrite($this->fh, "}\n\n");
	}

	public function finalMapping()
	{
		fwrite($this->fh, "# Final mapping\n");
		fwrite($this->fh, "map \$is_tablet \$is_mobile {\n");
		fwrite($this->fh, " default \$is_mobile;\n");
		fwrite($this->fh, " 1 1;\n");
		fwrite($this->fh, "}\n");
	}
}

$mobileConf = new MobileConfiguration();
$mobileConf->generate();









