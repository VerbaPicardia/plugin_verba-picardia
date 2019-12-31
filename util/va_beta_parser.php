<?php
class VA_BetaParser extends BetaParser {
	
	
	public function __construct ($source){
		global $va_xxx;
		
		parent::__construct($va_xxx, 'trules', 'tcodepage_original', $source, 'tcodepage_ipa', false);
		
		if($va_xxx->get_var($va_xxx->prepare('SELECT VA_Beta FROM Bibliographie WHERE Abkuerzung = %s', $source))){
			list ($rules, $optionals) = $this->rules_beta($va_xxx);
		}
		else if ($source == 'ALD-I' || $source == 'ALD-II'){
			list ($rules, $optionals) = $this->rules_ald();
			$this->va_beta = false;
		}
		else if ($source == 'BSA'){
			list ($rules, $optionals) = $this->rules_bsa();
			$this->va_beta = false;
		}
		else {
			throw new Exception('Not possible for source "' . $source . '"');
		}
		
		if($source === 'ALP' || $source === 'ALJA' || $source === 'ALL'){ //TODO document
			$this->default_accent_for_ipa(true);
		}

		
		$this->init($rules, $optionals);
	}
	
	private function rules_ald (){
		$grammarData = [];
		
		$grammarData['Beleg'] = [['sequence', [
			['identifier', 'Token'],
			['repeat', ['sequence', [['identifier', 'Leerzeichen'], ['identifier', 'Token']]], 0, INF]
		]], 'array'];
		
		$grammarData['Token'] = [['repeat', ['choice', [['identifier', 'Zeichen'], ['identifier', 'Eingeklammert']]], 0, INF], 'array'];
		
		$grammarData['Eingeklammert'] = [['choice', [
			['sequence', [['literal', '1<'], ['identifier', 'Zeichen'], ['literal', '1>']]],
			['sequence', [['literal', '1('], ['identifier', 'Zeichen'], ['literal', '1)']]]
		]], 'string'];
		
		$grammarData['Zeichen'] = [['sequence', [['identifier', 'Schriftart'], ['identifier', 'Buchstabe']]], 'string'];
		
		$grammarData['Schriftart'] = [['characterClass', '1-7'], 'string'];
		
		$grammarData['Buchstabe'] = [['characterClass', 'a-zA-Záéíóúèìòùüäëöç0-9'], 'string'];
		
		$grammarData['Leerzeichen'] = [['literal', '1 '], 'string'];
		
		return [$grammarData, []];
	}
	
	private function rules_bsa (){
		$grammarData = [];
		
		$grammarData['Beleg'] = [['sequence', [
			['identifier', 'Token'],
			['repeat', ['sequence', [['identifier', 'Leerzeichen'], ['identifier', 'Token']]], 0, INF]
		]], 'array'];
		
		$grammarData['Token'] = [['repeat', ['identifier', 'Zeichen'], 0, INF], 'array'];
		
		$grammarData['Zeichen'] = [['sequence', [['identifier', 'Basiszeichen'], ['repeat', ['identifier', 'Diakritikum'], 0, INF]]], 'string'];
		
		$grammarData['Basiszeichen'] = [['characterClass', 'A-Z'], 'string'];
		
		$grammarData['Diakritikum'] = [['characterClass', '0-9+\-$.,;=:&$\'%'], 'string'];
		
		$grammarData['Leerzeichen'] = [['literal', ' '], 'string'];
		
		return [$grammarData, []];
	}
}