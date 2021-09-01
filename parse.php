<?php
/**
 * Predmet: IPP 2021
 * Popis: Projekt c.1 - Analyzator kodu v IPPcode21
 * Nazov suboru: parse.php
 * Autor: Tomas Zatko (xzatko02)
 * Datum: 11.2.2021
 */
 
ini_set('display_errors', 'stderr');	// povinne zo zadania
$xml = new DOMDocument('1.0', 'UTF-8');	// vytvorenie DOMDocument
$xml->preserveWhiteSpace = false;		// nastavenia DOMDocumentu formatovania
$xml->formatOutput = true;
kontrola_argumentov();					// funkcia pre kontrolu vstupnych argumentov
kontrola_suboru();						// funkcia pre kontrolu hlavicky vstupneho suboru
zapis_xml($xml);						// funkcia pre zapis a ulozenie dat do xml suboru, kontrolu argumentov jednotlivych prikazov
exit(0);								// uspesne ukoncenie programu

// funkcia pre kontrolu vstupnych argumentov
function kontrola_argumentov()
{
	global $argc;					// pocet argumentov poslanych skriptu
	$mozne_argumenty_set = array("help");
	$mozne_argumenty = getopt(null, $mozne_argumenty_set);				// ziska moznosti vyskytu argumentov predanych cez prikazovy riadok
	$mozne_argumenty_rozsirenia_set = array("stats:", "comments", "labels", "fwjumps", "loc", "jumps", "badjumps", "testlist:", "match:", "directory"); // priprava pre rozsirenia
	$mozne_argumenty_rozsirenia = getopt(null, $mozne_argumenty_set);	// ziska moznosti argumentov
	if($argc == 2) {	// zadany jeden argument
		if(array_key_exists('help', $mozne_argumenty)) {	// kontrola ci zadana polozka existuje v danom poli
			fprintf(STDOUT, "Skript typu filter (parse.php v jazyku PHP 7.4) nacita zo standardneho vstupu zdrojovy kod v IPP-code21,\nskontroluje lexikalnu a syntakticky spravnost kodu a vypise na standardny vystup XML reprezentaciu programu podla specifikacie v sekcii 3.1.\n");
			fprintf(STDOUT, "21 - chybna alebo chybajuca hlavicka v zdrojovom kode zapisanom v IPPcode21\n");
			fprintf(STDOUT, "22 - neznamy alebo chybny operacny kod v zdrojovom kode zapisanom v IPPcode21\n");
			fprintf(STDOUT, "23 - ina lexikalna alebo syntakticka chyba zdrojoveho kodu zapisanom v IPPcode21\n");
			exit(0);
		} else {
			exit(10);	
		}
	} elseif($argc == 1) {	// neboli zadane ziadne argumenty v prikazovom riadku
		return;				// je to v poriadku, osetrenie nastava pri funkcii kontrola_suboru
	} else {
        fprintf(STDERR,"Nesprávny počet alebo kombinácia zadaných argumentov!\n");
        exit(10);			// nemozno kombinovat s inym parametrom
    }
}

// funkcia pre kontrolu hlavicky vstupneho suboru
function kontrola_suboru()
{
	$obsah_suboru = 'start';	// nedolezita inicializacia
	while($obsah_suboru != ".ippcode21") {	// postupne nacitavanie riadku za riadkov pre zistenie vyskytu povinnej hlavicky ".ippcode21"
		$obsah_suboru = fgets(STDIN);
		if(!$obsah_suboru){
			fprintf(STDERR,"Súbor obsahuje chybnú alebo chýbajúcu hlavičku.");
			exit(21);
		} else {
			$obsah_suboru = strtolower($obsah_suboru);	// pre lahsiu kontrolu zmena na male pismena - podla zadania nezalezi na velkosti pismen
			$obsah_suboru = preg_replace("/#.*$/", "", $obsah_suboru, -1, $pocitadlo);	// nahradenie bezpocetneho limitu znakov # za whitespaces
			$obsah_suboru = trim($obsah_suboru); 		// pre odstranenie whitespaces
			if($obsah_suboru != ".ippcode21" && $obsah_suboru != ""){
				fprintf(STDERR,"Súbor obsahuje chybnú alebo chýbajúcu hlavičku.");
				exit(21); // header nie je ako prvy v kode - JE SPRAVNY ERROR CODE?????
			}
		}
	}
	return;
}

// funkcia pre zapis a ulozenie dat do xml suboru, kontrolu argumentov jednotlivych prikazov
function zapis_xml($xml)
{	// praca s xml suborom
	$zaciatok_xml = $xml->createElement('program');		// vytvaram novu instanciu a zapisujem hlavicku
	$zaciatok_xml = $xml->appendChild($zaciatok_xml);
	$jazyk = $xml->createAttribute("language");
	$jazyk->value = "IPPcode21";
	$zaciatok_xml->appendChild($jazyk);
	$order_number = 0;
	
	while($line = fgets(STDIN)) {									// nacitavanie riadok po riadku zo STDIN
		$line = preg_replace("/#.*$/", "", $line, -1, $pocitadlo);	// z kazdeho riadku vymaze znak # a vsetko za nim
		$line = trim($line);										// odstran whitespaces zo zaciatku riadku
		if($line == '') {											// pri prazdnom riadku pokracuj hned dalsou iteraciou
			continue;
		}
		$line = preg_replace('/\s+/', ' ', $line);					// vsetky whitespaces v riadku nahradim len jednym whitespace
		$rozdelena_line = najdi_opcode($line);						// z kazdeho riadku extrahujem opcode (instrukciu)
		$element_instruction = $xml->createElement("instruction");	// zapisujem do xml formatu instrukciu s jej cislom
		$zaciatok_xml->appendChild($element_instruction);
		$attribute_order = $xml->createAttribute("order");
		$order_number = $order_number + 1;
		$attribute_order->value = $order_number;
		$element_instruction->appendChild($attribute_order);
		strtoupper($rozdelena_line[0]);								// pozor! nikam to neukladam, no osetrene v najdi_opcode funkcii
		switch($rozdelena_line[0]) {
			// <var>
			case 'DEFVAR':
			case 'POPS':
				if(count($rozdelena_line) == 2)	{					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 1;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 1, 0, 0, 0, 0, 0);	// kontrolujem a priradujem typ premennej
				} else {
					exit(23);
				}
				break;
				
			// < >
			case 'BREAK':
			case 'RETURN':
			case 'CREATEFRAME':
			case 'PUSHFRAME':
			case 'POPFRAME':
				if(count($rozdelena_line) == 1) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 0;
					$typ_premennej[0] = '';
					break;
				} else {
					exit(23);
				}
				break;
				
			// <symb>
			case 'WRITE':
			case 'DPRINT':
			case 'PUSHS':
				if(count($rozdelena_line) == 2) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 1;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 1, 1, 1, 1, 1, 0);
				} else {
					exit(23);
				}
				break;
			
			// <symb>
			case 'EXIT':
				if(count($rozdelena_line) == 2) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 1;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 1, 1, 1, 1, 1, 0);
				} else {
					exit(23);
				}
				break;
				
			// <var> <symb>
			case 'MOVE':
			case 'STRLEN':
			case 'INT2CHAR':
			case 'TYPE':
			case 'NOT':
				if(count($rozdelena_line) == 3) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 2;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 1, 0, 0, 0, 0, 0);
					list($typ_premennej[2], $rozdelena_line[2]) = kontrola_typu($rozdelena_line, 2, 1, 1, 1, 1, 1, 0);
				} else {
					exit(23);
				}
				break;
				
			// <label>
			case 'CALL':
			case 'LABEL':
			case 'JUMP':
				if(count($rozdelena_line) == 2) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 1;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 0, 0, 0, 0, 0, 1);
				} else {
					exit(23);
				}
				break;
			
			// <var><symb1><symb2>	
			case 'ADD':
			case 'SUB':
			case 'MUL':
			case 'IDIV':
			case 'LT':
			case 'GT':
			case 'EQ':
			case 'AND':
			case 'OR':
			case 'CONCAT':
			case 'STRI2INT':
			case 'GETCHAR':
			case 'SETCHAR':
				if(count($rozdelena_line) == 4) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 3;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 1, 0, 0, 0, 0, 0);
					list($typ_premennej[2], $rozdelena_line[2]) = kontrola_typu($rozdelena_line, 2, 1, 1, 1, 1, 1, 0);
					list($typ_premennej[3], $rozdelena_line[3]) = kontrola_typu($rozdelena_line, 3, 1, 1, 1, 1, 1, 0);
				} else {
					exit(23);
				}
				break;
				
			// <var><type>
			case 'READ':
				if(count($rozdelena_line) == 3){					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 2;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 1, 0, 0, 0, 0, 0);
					if($rozdelena_line[2] == 'int' or $rozdelena_line[2] == 'string' or $rozdelena_line[2] == 'bool'){
						$typ_premennej[2] = 'type';
					}
					else {
						exit(23);
					}
				} else {
					exit(23);
				}
				break;
				
			// <label><symb1><symb2>
			case 'JUMPIFEQ':
			case 'JUMPIFNEQ':
				if(count($rozdelena_line) == 4) {					// povoleny pocet slov v riadku pre danu instrukciu
					$pocet_argumentov = 3;
					list($typ_premennej[1], $rozdelena_line[1]) = kontrola_typu($rozdelena_line, 1, 0, 0, 0, 0, 0, 1);
					list($typ_premennej[2], $rozdelena_line[2]) = kontrola_typu($rozdelena_line, 2, 1, 1, 1, 1, 1, 0);
					list($typ_premennej[3], $rozdelena_line[3]) = kontrola_typu($rozdelena_line, 3, 1, 1, 1, 1, 1, 0);
				} else {
					exit(23);
				}
				break;	
		}
		$attribute_opcode = $xml->createAttribute("opcode");		// do xml formatu zapisujem opcode 
		$attribute_opcode->value = $rozdelena_line[0];
		$element_instruction->appendChild($attribute_opcode);
		for($arg_type_counter = 1; $arg_type_counter <= $pocet_argumentov; $arg_type_counter = $arg_type_counter + 1) {	// zapisujem argumenty a ich datove typy a nazvy
		$element_arg = $xml->createElement("arg".$arg_type_counter);
		$element_instruction->appendChild($element_arg);
		$attribute_type = $xml->createAttribute("type");
		$attribute_type->value = $typ_premennej[$arg_type_counter];
		$element_arg->appendChild($attribute_type);
		$text_variable = $xml->createTextNode($rozdelena_line[$arg_type_counter]);
		$element_arg->appendChild($text_variable);
		}
	} echo $xml->saveXML();											// ukoncenie zapisu do XML a vypis
	return $xml;	
}

// funkcia pre kontrolu opcode a rozdelenie riadku podla medzier
function najdi_opcode($line)
{	
	$mozne_opcodes = array("MOVE", "CREATEFRAME", "PUSHFRAME", "POPFRAME", "DEFVAR", "CALL", "RETURN", "PUSHS", "POPS", "ADD", "SUB", "MUL", "IDIV", "LT", "GT", "EQ", "AND", "OR", "NOT", "INT2CHAR", "STRI2INT", "READ", "WRITE", "CONCAT", "STRLEN", "GETCHAR", "SETCHAR", "TYPE", "LABEL", "JUMP", "JUMPIFEQ", "JUMPIFNEQ", "EXIT", "DPRINT", "BREAK");
	$rozdelena_line = trim($line, "\n");		// odstranim znak newline z riadku
	$rozdelena_line = explode(" ", $line);		// rozdelim riadok pomocou funkcie explode podla medzier
	$rozdelena_line[0] = strtoupper($rozdelena_line[0]);	// prevediem na velke pismena
	if(in_array($rozdelena_line[0], $mozne_opcodes)) {
		return $rozdelena_line;
	} else {
		exit(22);
	}
}

// funkcia pre kontrolu a urcenie typu
// prijima informacie o moznych datovych typov pomocou nastavenia hodnot v prijimanych parametroch
// pomocou regexov kontrolujem spravnost hodnot
function kontrola_typu(&$rozdelena_line_tmp, $i, $var, $int, $string, $bool, $nil, $label)
{
	$empty_string = 'string@';		// premenna pre osetrenie situacie, kedy je v ippcode21 prazdny string
	if(preg_match("/^(GF|LF|TF)@[[:alpha:]!?%*$&_-][[:alnum:]!?%*$&_-]*/", $rozdelena_line_tmp[$i]) && $var == 1) {
		$typ_premennej[$i] = 'var';
	} elseif(preg_match("/^(int)@[-|+]?[[:digit:]]+$/", $rozdelena_line_tmp[$i]) && $int == 1) {
		$typ_premennej[$i] = 'int';
	} elseif((preg_match("/^(string)@/", $rozdelena_line_tmp[$i]) && $string == 1) || (($rozdelena_line_tmp[$i] == $empty_string) && $string == 1)) {
		$typ_premennej[$i] = 'string';
	} elseif(preg_match("/^(bool)@(false|true)/", $rozdelena_line_tmp[$i]) && $bool == 1) {
		$typ_premennej[$i] = 'bool';
	} elseif(preg_match("/^(nil)@nil$/", $rozdelena_line_tmp[$i]) && $nil == 1) {
		$typ_premennej[$i] = 'nil';
	} elseif(preg_match("/^[[:alpha:]!?%*$&_-][[:alnum:]!?%*$&_-]*$/", $rozdelena_line_tmp[$i]) && $label == 1) {
		$typ_premennej[$i] = 'label';		
	} else {
		exit(23);
	}
	// pokial je datovy typ var alebo label, tak rozdelujem nazov za vyskytom znamienka '@'
	if($typ_premennej[$i] != 'var' && $typ_premennej[$i] != 'label') {
		$rozdelena_line_tmp[$i] = substr($rozdelena_line_tmp[$i], strpos($rozdelena_line_tmp[$i], "@") + 1);
	}
	// vraciam pole s datovym typom argumentu a obsahom danej premennej
	return array($typ_premennej[$i], $rozdelena_line_tmp[$i]);
}
?>