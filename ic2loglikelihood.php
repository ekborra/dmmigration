<?
/*
 *      Copyright (C) 2010 Erik Borra
 *
 *      Gets log likelihood for the homepages of hosts in the core network of an Issue Crawler xml file
 *	    Enter the network_id and call from the command line.
 *
 *      @author: Erik Borra - mail [didelidoo] erikborra.net
 *
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or (at
 *      your option) any later version.
 *
 *      This program is distributed in the hope that it will be useful, but
 *      WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *      General Public License for more details.
 *
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software Foundation,
 *      Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

$network_id = 318905;
$results_dir = $network_id."/";

// make a dir to store the results
if(!file_exists($results_dir)) mkdir($results_dir);

// do all steps necessary to get a log likelihood for each document
$hosts = retrieve_hosts_from_xml($network_id);
$file_list = retrieve_frontpages_and_store($hosts);
list($term_frequencies,$document_frequency) = get_term_and_document_frequencies($file_list);
calculate_log_likelihood_and_store($term_frequencies,$document_frequency);
print "done\n";

/*
 * Function: calculate_log_likelihood_and_store
 * Calculates the log likelihood for a set of term frequencies and the document frequency
 *
 * Parameters:
 *		$term_frequency - mapping terms with their frequency in one document
 *		$document_frequency - mapping terms with their frequency in all documents 
 */
 function calculate_log_likelihood_and_store($term_frequencies,$document_frequency) {
	global $results_dir;
	
	print "calculating log likelihood\n";
	
	foreach($term_frequencies as $file_name => $term_frequency) {
		$ll = get_log_likelihood_from_vectors($document_frequency,$document_frequency,$term_frequency);
		
		if(!file_exists($results_dir."ll")) mkdir($results_dir."ll");
		$handle = fopen($results_dir."ll/".str_replace(".html",".tsv",$file_name),"w");
		foreach($ll as $term => $critical_value) {
			if($critical_value >= 3.84) { // only store terms in the 95% confidence percentile
				fwrite($handle,"$term\t$critical_value\n");
			}
		}
		fclose($handle);
	}
}

/*
 * Function: get_log_likelihood_from_vectors
 * Calculates the log likelihood for a term frequency list and its document frequency list (see http://ucrel.lancs.ac.uk/llwizard.html for more info)
 * Returns an associative array of terms mapped with their critical value
 *
 * Parameters:
 * 		$terms - document_frequency mapping terms with their frequency in all documents
 *		$document_frequency - mapping terms with their frequency in all documents
 *		$term_frequency - mapping terms with their frequency in one document
 */
function get_log_likelihood_from_vectors($terms,$document_frequency,$term_frequency) {
	$document_frequency_count = array_sum($document_frequency);
	$term_frequency_count = array_sum($term_frequency);
	$cnt = $document_frequency_count + $term_frequency_count;

	foreach($terms as $term => $frequency) {
		if(array_key_exists($term,$document_frequency)===false) $document_frequency[$term] = 0.1;
		if(array_key_exists($term,$term_frequency)===false) $term_frequency[$term] = 0.1;
		$critical_value = $document_frequency[$term] * log($document_frequency[$term] / ($document_frequency_count * $terms[$term] / $cnt)) 
				  		+ $term_frequency[$term] * log($term_frequency[$term] / ($term_frequency_count * $terms[$term] / $cnt));
		$ll[$term] = $critical_value;
	}
	arsort($ll);
	return $ll;
}

/*
 * Function: get_term_and_document_frequencies
 * Strips the tags from a set of html files, retrieves the terms and their frequencies from those files
 * Returns two associative arrays,
 *	One associative array maps a file name and the term frequencies in that file
 *  The other associative array is the term frequency of all terms in all files
 *
 * Parameters:
 * 		$file_list - a list of locations of html files
 */
function get_term_and_document_frequencies($file_list) {
	print "getting term frequencies\n";
	
	$document_frequency = array();
	foreach($file_list as $key => $file_name) {
		$stripped = strip_html_and_store($file_name);
		$term_frequencies[$file_name] = get_term_frequency_and_store($stripped);
	
		// keep track of all words in all documents
		foreach($term_frequencies[$file_name] as $term => $frequency) {
			if(array_key_exists($term,$document_frequency)!==false) $document_frequency[$term]+=$frequency;
			else $document_frequency[$term]=$frequency;
		}
	}
	return array($term_frequencies,$document_frequency);
}

/*
 * Function: get_term_frequency_and_store
 * Returns an associative array of terms and their frequency present in $file
 *
 * Parameters:
 * 		$file - a string of text
 */
function get_term_frequency_and_store($file_name) {
	global $results_dir; 
	
	$file = implode('',file($results_dir."stripped/".str_replace(".html",".txt",$file_name)));
	
	// find terms
	$terms = split("[ ]+", $file);
	$term_frequency = array();
	foreach($terms as $k => $term) {
		// only store terms longer than 3 chars
		$term = trim(html_entity_decode($term));
		if(strlen($term)>3)
			$terms[$k] = strtolower($term); // lowercase the terms
		else
			unset($terms[$k]);
	}
	
	// count words
	$term_frequency = array_count_values($terms);
	arsort($term_frequency);

	// write term frequencies to file
	if(!file_exists($results_dir."tf")) mkdir($results_dir."tf");
	$handle = fopen($results_dir."tf/".str_replace(".html",".tsv",$file_name),"w");
	foreach($term_frequency as $term => $frequency) fwrite($handle,"$term\t$frequency\n");
	fclose($handle);
	
	return $term_frequency;	
}

/*
 * Function: strip_html_and_store
 * Strips htmls and returns the file name of the stored file
 *
 * Parameters:
 * 		$file_name - the name of a store html file
 */
function strip_html_and_store($file_name) {
	global $results_dir;
	
	// read stored html file
	print $results_dir."html/".$file_name."\n";
	$file = implode('',file($results_dir."html/".$file_name));

	// strip tags and unnecessary white spaces
	$file = preg_replace("/<style[^<]*?<\/style/msxi"," ",$file);
	$file = preg_replace("/<script.*?<\/script/msxi"," ",$file);	
	$file = strip_tags($file);
	$file = preg_replace("/([\s|\r|\n]{1,})/"," ",$file);

	// write stripped text
	if(!file_exists($results_dir."stripped")) mkdir($results_dir."stripped");	
	$handle = fopen($results_dir."stripped/".str_replace(".html",".txt",$file_name),"w");
	fwrite($handle,$file);
	fclose($handle);
	
	return $file_name;
}

/*
 * Function: sanitize
 * Returns a sanitized string, typically for URLs.
 *
 * Parameters:
 *     $string - The string to sanitize.
 *     $force_lowercase - Force the string to lowercase?
 *     $anal - If set to *true*, will remove all non-alphanumeric characters.
 */
function sanitize($string, $force_lowercase = true, $anal = false) {
    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                   "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                   "â€”", "â€“", ",", "<", ".", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', "-", $clean);
    $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
    return ($force_lowercase) ?
        (function_exists('mb_strtolower')) ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean) :
        $clean;
}

/**
 * Function: retrieve_frontpages_and_store
 * Retrieves a html page and stores it
 * Returns an associative array with url -> file name mappings
 *
 * Parameters:
 *		$urls - An array of urls
 */
function retrieve_frontpages_and_store($urls) {
	global $results_dir;
	
	print "retrieving urls\n";
	
	if(!file_exists($results_dir."html")) exec("mkdir ".$results_dir."html");
	
	$file_names = $urls_2_filenames = array();
	foreach($urls as $url) {

		// make file name from url
		$file_name = sanitize($url).".html";		
		$url_to_file = $url."\t".$file_name;
		print "\tretrieving $url_to_file\n";
		
		// keep track of name
		$file_names[] = $url_to_file;
		$urls_2_filenames[$url] = $file_name;
		
		// retrieve and store html file
		$file = implode('',file($url));
		$handle = fopen($results_dir."html/".$file_name,"w");
		fwrite($handle,$file);	
		fclose($handle);
	}

	// store url to file name mapping
	$handle = fopen($results_dir."host2filename.csv","w");
	fwrite($handle,implode("\n",$file_names));
	fclose($handle);
	
	return $urls_2_filenames;
}
 
/*
 * Function: retrieve_hosts_from_xml
 * Returns the list of host names from the core network of an Issue Crawler xml file
 *
 * You can skip this step if you have a list of urls somewhere else
 *
 * Parameters:
 * 		$network_id - an Issue Crawler network id
 */
function retrieve_hosts_from_xml($network_id) {
	global $results_dir;
	
	print "retrieving hosts from xml $network_id\n";
	
	// download the xml file
	$file = implode('',file("http://issuecrawler.net/network/download_xml.php?network_id=".$network_id));
	// retrieve all core pages
	preg_match_all("/Page URL=\"(.+?)\"/",$file,$matches);
	// get the host part of each page
	foreach($matches[1] as $m) {
		$purl = parse_url($m);
		$host = $purl['scheme']."://".strtolower($purl['host']);
		$hosts[] = $host;
	}
	// return a unique list of hosts
	return array_unique($hosts);
}
?>