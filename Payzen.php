<?php
class Payment_Adapter_Payzen
{
    private $config = array();

    public function __construct($config)
    {
        $this->config = $config;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  true,
            'description'     =>  'Paiement Payzen.',
            'form'  => array(
                'vads_site_id' => array('text', array(
                            'label' => 'Identifiant Boutique',
                    ),
                ),
                'TEST_key' => array('text', array(
                            'label' => 'Certificat Test',
                    ),
                ),
				'PROD_key' => array('text', array(
                            'label' => 'Certificat Prod',
                    ),
                ),
            ),
        );
    }

    /**
     * Generate payment text
     *
     * @param Api_Admin $api_admin
     * @param int $invoice_id
     * @param bool $subscription
     *
     * @since BoxBilling v2.9.15
     *
     * @return string - html form with auto submit javascript
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
		$buyer = $invoice['buyer'];
		
		if ($this->config['test_mode']){$mode='TEST';}
		else {$mode='PROD';}
		
		// Make sur invoice ID is numeric and zero-fill to 6 digits
		//$trans_id = sprintf("%06d", $invoice_id); // Not unique...

		// The transaction ID has to be unique in a same day. As a result, it is set as the hours-minutes-seconds.
		$trans_id = date("His");
		
		$payment = array(
			'vads_site_id'				=> $this->config['vads_site_id'],
			'vads_ctx_mode'				=> $mode,
			'vads_url_return'			=> BB_URL.'/dashboard', // URL de retour - facture = $this->config['return_url']
			'vads_url_check'			=> $this->config['notify_url'], // URL de check IPN
			'vads_return_mode'			=> 'GET', // Retour GET ou POST?
			'vads_trans_id'				=> $trans_id,
			'vads_trans_date'			=> gmdate('YmdHis'),
			'vads_action_mode'			=> 'INTERACTIVE',
			'vads_version'				=> 'V2',
			'vads_payment_config'		=> 'SINGLE',
			'vads_amount'				=> $invoice['total'] * 100, // Total in cents. E.g., 3000 for 30€
			'vads_currency'				=> '978', // 978=Euro, 840=dollar
			'vads_capture_delay'		=> 0,
			'vads_validation_mode'		=> 0,	// Automatic
			'vads_cust_email'			=> $buyer['email'],	// Buyer email
			'vads_cust_id'				=> $buyer['email'],	// Buyer id
			//'vads_cust_title'			=> '',	// Buyer title
			'vads_cust_status'			=> 'PRIVATE',	// Buyer status - PRIVATE / COMPANY
			'vads_cust_first_name'		=> $buyer['first_name'],	// Buyer first name
			'vads_cust_last_name'		=> $buyer['last_name'],	// Buyer last name
			//'vads_cust_legal_name'		=> '',	// Buyer 'raison sociale'
			//'vads_cust_cell_phone'		=> '',	// Buyer cell phone number
			'vads_cust_phone'			=> $buyer['phone_cc'].$buyer['phone'],	// Buyer phone number
			'vads_cust_address_number'	=> '',	// Buyer address number
			'vads_cust_address'			=> $buyer['address'],	// Buyer address
			//'vads_cust_district'		=> '',	// Buyer district
			'vads_cust_zip'				=> $buyer['zip'],	// Buyer postal code
			'vads_cust_city'			=> $buyer['city'],	// Buyer city
			'vads_cust_state'			=> $buyer['state'],	// Buyer state/region
			'vads_cust_country'			=> $buyer['country'],	// Buyer ISO 3166 country code
			'vads_langague'				=> 'fr',	// Language: fr, en, de...
			'vads_order_id'				=> $invoice['id'],	// Order ID
			'vads_shop_name'			=> $invoice['seller']['company'],	// Company name
			'vads_shop_url'				=> BB_URL,	// Shop URL
			//'vads_order_info'			=> '',	// Order description
			// 3DSecure?
			// Shipping address?
			// vads_contracts="CB=12312312;AMEX=949400444000;PAYPAL=nom@paypal.com"
			// vads_payment_cards="VISA;MASTERCARD;VISA_ELECTRON;"...

/* vads_nb_products
vads_product_labelN
vads_product_amountN
vads_product_typeN   // SERVICE_FOR_INDIVIDUAL, COMPUTER_AND_SOFTWARE
vads_product_refN
vads_product_qtyN
vads_shipping_amount
vads_tax_amount
vads_insurance_amount
vads_amount =  (vads_product_qty(N)  x  vads_product_amount(N)  )  +  vads_shipping_amount  + vads_tax_amount + vads_insurance_amount
*/

		// TBD : abonnement
/*
vads_page_action SUBSCRIBE
vads_sub_amount: Montant des échéances de l’abonnement (dans sa plus petite unité monétaire).
vads_sub_effect_date: Date de début de l'abonnement. Ex : 20150601
vads_sub_currency: currency (978 euro)
vads_sub_desc: Règle de récurrence à appliquer suivant la spécification iCalendar RFC5545. Ex : RRULE:FREQ=MONTHLY;COUNT=12;BYMONTHDAY=10 (pour des échéances de paiement ayant lieu le 10 de chaque mois, pendant 12 mois)
*/

		);

		if($subscription) {
			// Subscription, sets specific fields

			// Sets up the RRULE. 
			$subs = $invoice['subscription'];
			switch ($subs['unit']) {
		        case 'W':
		            $freq = 'WEEKLY';
					$count = 52;
		            break;
		        case 'Y':
		            $freq = 'YEARLY';
					$count = 1;
		            break;
		        case 'M':
		        default:
		            $freq = 'MONTHLY';
					$count = 12;
		            break;
		    }

			$payment['vads_page_action'] = 'REGISTER_PAY_SUBSCRIBE';
			$payment['vads_sub_amount'] = $invoice['total'] * 100; // Total in cents. E.g., 3000 for 30€
			$payment['vads_sub_currency'] = '978'; // 978=Euro, 840=dollar
			$payment['vads_sub_effect_date'] = date("Ymd");
			$payment['vads_sub_desc'] = 'RRULE:FREQ='.$freq.';COUNT='.$count.';'; // Règle de récurrence à appliquer suivant la spécification iCalendar RFC5545. Ex : RRULE:FREQ=MONTHLY;COUNT=12;BYMONTHDAY=10 (pour des échéances de paiement ayant lieu le 10 de chaque mois, pendant 12 mois). The subscription can last only for one year maximum
        } 
		else {
			// Single payment, sets specific fields
			$payment['vads_page_action'] = 'PAYMENT';
        }

		$payment['signature'] = $this->_getSignature($payment);

		$url = 'https://secure.payzen.eu/vads-payment/';
        return $this->_generateForm($url, $payment);
    }

    /**
     * Process transaction received from payment gateway
     *
     * @since BoxBilling v2.9.15
     *
     * @param Api_Admin $api_admin
     * @param int $id - transaction id to process
     * @param array $ipn - post, get, server, http_raw_post_data
     * @param int $gateway_id - payment gateway id on BoxBilling
     *
     * @return mixed
     */
    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $transaction = $api_admin->invoice_transaction_get(array('id'=>$id));
		$ipn = $data['post'];

		$invoice_id = null;
        if(isset($transaction['invoice_id'])) {
            $invoice_id = $transaction['invoice_id'];
        } elseif(isset($data['get']['bb_invoice_id'])) {
            $invoice_id = $data['get']['bb_invoice_id'];
        }
		
		if(!$invoice_id) {
            throw new Payment_Exception('Invoice id could not be determined for this transaction');
        }
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));	


		$transaction['invoice_id'] = $invoice['id'];
		$transaction['txn_id'] = $ipn['vads_trans_id'];
		$transaction['amount'] = $ipn['vads_amount']/100;	// vads_amount is in cents
		$transaction['currency'] = $invoice['currency'];	// 978=Euro, 840=dollar
		$transaction['type'] = 'payment';
		$transaction['txn_status'] = 'pending';
		$api_admin->invoice_transaction_update($transaction);
		
		/*
			No HTML in the page
			The platform only reads the 512 first chars (shown in transaction history)
			Timeout after 35s
			
			Récupérer la liste des champs présents dans la réponse envoyée en POST
			Calculer la signature
			Comparer la signature calculée avec celle réceptionnée
			Analyser la nature de la notification
			Récupérer le résultat du paiement
		*/
		
		if (isset($ipn)) {
			if (isset($ipn['signature'])) {
				$calculated_signature = $this->_getSignature($ipn);
				$received_signature = $ipn['signature'];
					
				if ($this->_checkSignature($ipn)) {
					// Valid signature
					$return = $this->_showReturn($ipn);

					if ($ipn['vads_result'] == '00') {
						// Validate transaction
						$transaction['txn_status'] = 'complete';
						$transaction['status'] = 'processed';
						$transaction['error'] = '';
						$transaction['error_code'] = '';
						$transaction['updated_at'] = date('Y-m-d H:i:s');
						$api_admin->invoice_transaction_update($transaction);

						// Validate and pay invoice
						$client_balance['id'] = $invoice['client']['id'];
						$client_balance['amount'] = $ipn['vads_amount']/100;	// vads_amount is in cents
						$client_balance['description'] = 'Payzen ref '.$ipn['vads_trans_id'];
						$client_balance['type'] = 'Payzen';
						$client_balance['rel_id'] = $ipn['vads_trans_id'];
//throw new Exception(json_encode($client_balance));
						//$api_admin->client_balance_add_funds($client_balance);
						//$api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_balance['id']));
						//$api_admin->invoice_pay_with_credits(array('id'=>$invoice['id']));
						$api_admin->invoice_mark_as_paid(array('id'=>$invoice['id']));
						$api_admin->invoice_batch_activate_paid();

						if (!empty($ipn['vads_sub_desc'])) {
							$rrule = substr(explode(';',$ipn['vads_sub_desc'])[0],11); // Format to parse is RRULE:FREQ=MONTHLY;COUNT=12; So we remove the COUNT section after the first ; and the first 11 characters corresponding to RRULE:FREQ=
							switch ($rrule) {
								case 'WEEKLY':
									$recurrence = '1W';
									break;
								case 'YEARLY':
									$recurrence = '1Y';
									break;
								case 'MONTHLY':
								default:
									$recurrence = '1M';
									break;
								// Todo: deal with other cases
							}

							$subscription['client_id'] = $invoice['client']['id'];
							$subscription['gateway_id'] = $gateway_id;
							$subscription['currency'] = $invoice['currency']; // vads_sub_curency, 978=Euro, 840=dollar
							$subscription['sid'] = $ipn['vads_order_id'];
							$subscription['status'] = 'active';
							$subscription['period'] = $recurrence;
							$subscription['amount'] = $ipn['vads_sub_amount']/100; // vads_sub_amount is in cents
							$subscription['rel_type'] = 'invoice';
							$subscription['rel_id'] = $invoice['id'];

							$api_admin->invoice_subscription_create($subscription);
						}

					}
					//else if ($ipn['vads_trans_status'] == 'ABANDONED') {
						// The payment has been abandoned by the client. We can delete the transaction
						
					//}
					else {
						$transaction['txn_status'] = $return;
						$transaction['status'] = 'failed';
						$transaction['error'] = $return; // Not working
						$transaction['error_code'] = $ipn['vads_auth_result'];
						$transaction['updated_at'] = date('Y-m-d H:i:s');
						$api_admin->invoice_transaction_update($transaction);
						throw new Payment_Exception($return);
					}
				}
				else {
					// Invalid signature
					throw new Payment_Exception('Wrong signature received');
				}
			}
			else {
				throw new Payment_Exception('No signature');
			}
		}
		else {
			throw new Payment_Exception('No POST');
		}
		
		
		/*
			vads_url_check_src : PAY (paiement immédiat, diffré, abandonné/annulé) ; REC (abonnement)
			vads_operation_type : DEBIT / CREDIT
		*/
    }
	
	/* Calcul d'une signature à partir des données vads_
			Tri des champs vads_ par ordre alphabétique
			Code des champs en UTF-8
			Concaténation des valeurs de ces champs séparés par "+"
			Contaténation du résultat avec le certificat de test ou de production, en les séparant avec "+"
			Algorithme SHA-1 pour obtenir la signature
	*/
	private function _getSignature($params)
	{
		
		ksort($params); // tri des paramétres par ordre alphabétique
		$signature_content = "";
		foreach ($params as $key => $value)
		{
			if(substr($key,0,5) == 'vads_') {
				$signature_content .= $value."+";
			}
		}
		
		// On ajoute le certificat TEST ou PROD à la fin de la chaîne.
		if ($this->config['test_mode']){
			$signature_content .= $this->config['TEST_key'];
		}
		else {
			$signature_content .= $this->config['PROD_key'];
		}

		$signature = sha1($signature_content); // Conversion en SHA1
		
		return($signature);
	}
	
	/*
		Vérification de la validité d'une signature
	*/
	private function _checkSignature($params)
	{
		$true_signature = $this->_getSignature($params);
		$received_signature = $params['signature'];
		if ($received_signature != $true_signature) {return false;}
		else{return true;}
	}
	
	private function _generateForm($url, $params, $method = 'post')
    {
        $form = '';
        $form .= '<form name="payment_form" action="'.$url.'" method="'.$method.'">' . PHP_EOL;
        foreach($params as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Please click here to continue if this page does not redirect automatically in 5 seconds" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;

        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to Payzen.com'));
            $form .=  "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }
	
	private function _showReturn($params)
	{

		$Redirection_vers_la_boutique = "Redirection vers la boutique";

		$Signature_Valide = "Signature Valide";
		$Signature_Invalide = "Signature Invalide - ne pas prendre en compte le résultat de ce paiement";
		$La_transaction_est_un_débit = "La transaction est un débit ayant comme caractéristiques";

		$STATUT = "Statut";
		$Le_paiement_a_ete_abandonne = "Le paiement a été abandonné par le client. La transaction n’a pas été crée sur la plateforme de paiement et n’est donc pas visible dans le back office marchand.";
		$Le_paiement_a_ete_accepte = "Le paiement a été accepté et est en attente de remise en banque.";
		$Le_paiement_a_ete_refuse = "Le paiement a été refusé.";
		$La_transaction_est_en_attente_de_validation_manuelle = "La transaction a été acceptée mais elle est en attente de validation manuelle. C'est à la charge du marchand de valider la transaction pour demander la remise en banque depuis le back office marchand ou par requête web service. La transaction pourra être validée tant que le délai de capture n’a pas été dépassé. Si ce délai est dépassé alors le paiement bascule dans le statut Expiré. Ce statut expiré est définitif.";
		$La_transaction_est_en_attente_d_autorisation = "La transaction est en attente d’autorisation. Lors du paiement uniquement un prise d’empreinte a été réalisée car le délai de remise en banque est strictement supérieur à 7 jours. Par défaut la demande d’autorisation pour le montant global sera réalisée à j-2 avant la date de remise en banque.";
		$La_transaction_est_expiree = "La transaction est expirée. Ce statut est définitif, la transaction ne pourra plus être remisée en banque. Une transaction expire dans le cas d'une transaction créée en validation manuelle ou lorsque le délai de remise en banque (capture delay) dépassé.";
		$La_transaction_a_ete_annulee = "La transaction a été annulée au travers du back office marchand ou par une requête web service. Ce statut est définitif, la transaction ne sera jamais remise en banque.";
		$La_transaction_est_en_attente_d_auto_et_de_valid = "La transaction est en attente d’autorisation et en attente de validation manuelle. Lors du paiement uniquement un prise d’empreinte a été réalisée car le délai de remise en banque est strictement supérieur à 7 jours et le type de validation demandé est « validation manuelle ». Ce paiement ne pourra être remis en banque uniquement après une validation du marchand depuis le back office marchand ou par un requête web services.";
		$La_transaction_a_ete_remise_en_banque = "La transaction a été remise en banque. Ce statut est définitif.";

		$RESULTAT = "Résultat";
		$Paiement_realise_avec_succes = "Paiement réalisé avec succès.";
		$Le_commercant_doit_contacter_la_banque_du_porteur = "Le commerçant doit contacter la banque du porteur.";
		$Paiement_refuse = "Paiement refusé.";
		$Annule_par_le_client = "Paiement annulé par le client.";
		$Erreur_de_format = "Erreur de format de la requête. A mettre en rapport avec la valorisation du champ vads_extra_result.";
		$Erreur_technique = "Erreur technique lors du paiement.";

		$IDENTIFIANT = "Identifiant";

		$MONTANT = "Montant";

		$MONTANT_EFFECTIF = "Montant Effectif";

		$TYPE_DE_PAIEMENT = "Type de paiement";
		$Paiement_simple = "Paiement simple.";

		$NUMERO_DE_SEQUENCE = "Numéro de séquence";

		$RESULTAT_D_AUTO = "Résultat d'autorisation";
		$Transaction_approuvee = "Transaction approuvée ou traitée avec succès.";
		$Contacter_l_emetteur = "Contacter l’émetteur de carte.";
		$Accepteur_invalide = "Accepteur_invalide.";
		$Conserver_la_carte = "Conserver la carte.";
		$Ne_pas_honorer = "Ne pas honorer.";
		$Conserver_la_carte_special = "Conserver la carte, conditions spéciales.";
		$Approuver_apres_identification = "Approuver après identification.";
		$Transaction_invalide = "Transaction invalide.";
		$Montant_invalide = "Montant invalide.";
		$Numero_de_porteur_invalide = "Numéro de porteur invalide.";
		$Erreur_de_format = "Erreur de format.";
		$Identifiant_de_l_organisme = "Identifiant de l’organisme acquéreur inconnu.";
		$Date_de_validite_depassee = "Date de validité de la carte dépassée.";
		$Suspicion_de_fraude = "Suspicion de fraude.";
		$Carte_perdue = "Carte perdue.";
		$Carte_volee = "Carte volée.";
		$Provision_insuffisante = "Provision insuffisante ou crédit dépassé.";
		$Carte_absente = "Carte absente du fichier.";
		$Transaction_non_permise = "Transaction non permise à ce porteur.";
		$Transaction_interdite = "Transaction interdite au terminal.";
		$Suspicion_de_fraude = "Suspicion de fraude.";
		$L_accepteur_doit_contacter = "L’accepteur de carte doit contacter l’acquéreur.";
		$Montant_de_retrait_hors_limite = "Montant de retrait hors limite.";
		$Regles_de_securite_non_respectees = "Règles de sécurité non respectées.";
		$Reponse_non_parvenue = "Réponse non parvenue ou reçue trop tard.";
		$Arret_momentane = "Arrêt momentané du système.";
		$Emetteur_inaccessible = "Emetteur de cartes inaccessible.";
		$Mauvais_fonctionnement = "Mauvais fonctionnement du système.";
		$Transaction_dupliquee = "Transaction dupliquée.";
		$Echeance_de_la_tempo = "Echéance de la temporisation de surveillance globale.";
		$Serveur_indisponible = "Serveur indisponible routage réseau demandé à nouveau.";
		$Incident_domaine_initiateur = "Incident domaine initiateur.";

		$GARANTIE_DE_PAIEMENT = "Garantie de paiement";
		$Le_paiement_est_garanti = "Le paiement est garanti.";
		$Le_paiement_n_est_pas_garanti = "Le paiement n’est pas garanti.";
		$Suite_a_une_erreur = "Suite à une erreur technique, le paiement ne peut pas être garanti.";
		$Garantie_non_applicable = "Garantie de paiement non applicable.";

		$STATUT_3DS = "Statut 3DS";
		$Authentifie_3DS = "Authentifié 3DS.";
		$Erreur_Authentification = "Erreur Authentification.";
		$Authentification_impossible = "Authentification impossible.";
		$Essai_d_authentification = "Essai d’authentification.";
		$Non_renseigne = "Non renseigné.";

		$DELAI_AVANT_REMISE_EN_BANQUE = "Délai avant Remise en Banque";
		$JOURS = "jours";

		$MODE_DE_VALIDATION = "Mode de Validation";
		$Validation_Manuelle = "Validation Manuelle";
		$Validation_Automatique = "Validation Automatique";
		$Configuration_par_defaut_du_back_office_marchand = "Configuration par défaut du back office marchand";

		$Liste_des_parametres_receptionnes = "Liste des paramètres réceptionnés";

		$message='';

		/*
		$message .= $La_transaction_est_un_débit.":</b></u><br/><br/>";

		if (isset($params['vads_trans_status'])) $message .= "<b>".$STATUT."</b> (vads_trans_status): ".$params['vads_trans_status']."<br/>";
		if (isset($params['vads_trans_status'])) switch ($params['vads_trans_status']) {
			case "ABANDONED":
				$message .= $Le_paiement_a_ete_abandonne;
				break;
			case "AUTHORISED":
				$message .= $Le_paiement_a_ete_accepte;
				break;
			case "REFUSED":
				$message .= $Le_paiement_a_ete_refuse;
				break;
			case "AUTHORISED_TO_VALIDATE":
				$message .= $La_transaction_est_en_attente_de_validation_manuelle;
				break;
			case "WAITING_AUTHORISATION":
				$message .= $La_transaction_est_en_attente_d_autorisation;
				break;
			case "EXPIRED":
				$message .= $La_transaction_est_expiree;
				break;
			case "CANCELLED":
				$message .= $La_transaction_a_ete_annulee;
				break;
			case "WAITING_AUTHORISATION_TO_VALIDATE":
				$message .= $La_transaction_est_en_attente_d_auto_et_de_valid;
				break;
			case "CAPTURED":
				$message .= $La_transaction_a_ete_remise_en_banque;
				break;
		}

		if (isset($params['vads_result'])) $message .= "<br/><br/><b>".$RESULTAT."</b> (vads_result): ".$params['vads_result']."<br/>";
*/
		if (isset($params['vads_result'])) switch ($params['vads_result']) {
			case "00":
				$message .= $Paiement_realise_avec_succes;
				break;
			case "02":
				$message .= $Le_commercant_doit_contacter_la_banque_du_porteur;
				break;
			case "05":
				$message .= $Paiement_refuse;
				break;
			case "05":
				$message .= $Annule_par_le_client;
				break;
			case "30":
				$message .= $Erreur_de_format;
				break;
			case "96":
				$message .= $Erreur_technique;
				break;
		}

/*
		if (isset($params['vads_trans_id'])) $message .= "<br/><br/><b>".$IDENTIFIANT."</b> (vads_trans_id): ".$params['vads_trans_id'];

		if (isset($params['vads_amount'])) $message .= "<br/><br/><b>".$MONTANT."</b> (vads_amount): ".$params['vads_amount'];

		if (isset($params['vads_effective_amount'])) $message .= "<br/><br/><b>".$MONTANT_EFFECTIF."</b> (vads_effective_amount): ".$params['vads_effective_amount'];

		if (isset($params['vads_payment_config'])) $message .= "<br/><br/><b>".$TYPE_DE_PAIEMENT."</b> (vads_payment_config): ".$params['vads_payment_config']."<br/>";
		if (isset($params['vads_payment_config'])) switch ($params['vads_payment_config']) {
			case "SINGLE":
				$message .= $Paiement_simple;
				break;
		}

		if (isset($params['vads_sequence_number'])) $message .= "<br/><br/><b>".$NUMERO_DE_SEQUENCE."</b> (vads_sequence_number): ".$params['vads_sequence_number'];

		if (isset($params['vads_auth_result'])) $message .= "<br/><br/><b>".$RESULTAT_D_AUTO."</b> (vads_auth_result): ".$params['vads_auth_result']."<br/>";
		*/

		$message .= ' / ';
		
		if (isset($params['vads_auth_result'])) switch ($params['vads_auth_result']) {
			case 00:
				$message .= $Transaction_approuvee;
				break;
			case 02:
				$message .= $Contacter_l_emetteur;
				break;
			case 03:
				$message .= $Accepteur_invalide;
				break;
			case 04:
				$message .= $Conserver_la_carte;
				break;
			case 05:
				$message .= $Ne_pas_honorer;
				break;
			case 07:
				$message .= $Conserver_la_carte_special;
				break;
			case 08:
				$message .= $Approuver_apres_identification;
				break;
			case 12:
				$message .= $Transaction_invalide;
				break;
			case 13:
				$message .= $Montant_invalide;
				break;
			case 14:
				$message .= $Numero_de_porteur_invalide;
				break;
			case 30:
				$message .= $Erreur_de_format;
				break;
			case 31:
				$message .= $Identifiant_de_l_organisme;
				break;
			case 33:
				$message .= $Date_de_validite_depassee;
				break;
			case 34:
				$message .= $Suspicion_de_fraude;
				break;
			case 41:
				$message .= $Carte_perdue;
				break;
			case 43:
				$message .= $Carte_volee;
				break;
			case 51:
				$message .= $Provision_insuffisante;
				break;
			case 54:
				$message .= $Date_de_validite_depassee;
				break;
			case 56:
				$message .= $Carte_absente;
				break;
			case 57:
				$message .= $Transaction_non_permise;
				break;
			case 58:
				$message .= $Transaction_interdite;
				break;
			case 59:
				$message .= $Suspicion_de_fraude;
				break;
			case 60:
				$message .= $L_accepteur_doit_contacter;
				break;
			case 61:
				$message .= $Montant_de_retrait_hors_limite;
				break;
			case 63:
				$message .= $Regles_de_securite_non_respectees;
				break;
			case 68:
				$message .= $Reponse_non_parvenue;
				break;
			case 90:
				$message .= $Arret_momentane;
				break;
			case 91:
				$message .= $Emetteur_inaccessible;
				break;
			case 96:
				$message .= $Mauvais_fonctionnement;
				break;
			case 94:
				$message .= $Transaction_dupliquee;
				break;
			case 97:
				$message .= $Echeance_de_la_tempo;
				break;
			case 98:
				$message .= $Serveur_indisponible;
				break;
			case 99:
				$message .= $Incident_domaine_initiateur;
				break;
		}

		/*
		if (isset($params['vads_warranty_result'])) $message .= "<br/><br/><b>".$GARANTIE_DE_PAIEMENT."</b> (vads_warranty_result): ".$params['vads_warranty_result']."<br/>";
		if (isset($params['vads_warranty_result'])) switch ($params['vads_warranty_result']) {
			case "YES":
				$message .= $Le_paiement_est_garanti;
				break;
			case "NO":
				$message .= $Le_paiement_n_est_pas_garanti;
				break;
			case "UNKNOWN":
				$message .= $Suite_a_une_erreur;
				break;
			default:
				$message .= $Garantie_non_applicable;
				break;
		}

		if (isset($params['vads_threeds_status'])) $message .= "<br/><br/><b>".$STATUT_3DS."</b> (vads_threeds_status): ".$params['vads_threeds_status']."<br/>";
		if (isset($params['vads_threeds_status'])) switch ($params['vads_threeds_status']) {
			case "Y":
				$message .= $Authentifie_3DS;
				break;
			case "N":
				$message .= $Erreur_Authentification;
				break;
			case "U":
				$message .= $Authentification_impossible;
				break;
			case "A":
				$message .= $Essai_d_authentification;
				break;
			default:
				$message .= $Non_renseigne;
				break;
		}

		if (isset($params['vads_capture_delay'])) $message .= "<br/><br/><b>".$DELAI_AVANT_REMISE_EN_BANQUE."</b> (vads_capture_delay): ".$params['vads_capture_delay']." ".$JOURS;

		if (isset($params['vads_validation_mode'])) $message .= "<br/><br/><b>".$MODE_DE_VALIDATION."</b> (vads_validation_mode): ".$params['vads_validation_mode']."<br/>";
		if (isset($params['vads_validation_mode'])) switch ($params['vads_validation_mode']) {
			case 1:
				$message .= $Validation_Manuelle;
				break;
			case 0:
				$message .= $Validation_Automatique;
				break;
			default:
				$message .= $Configuration_par_defaut_du_back_office_marchand;
				break;
		}
		*/
		
		return $message;
			// --------------------------------------------------------------------------------------
			// Affichage des paramètres recus 
			// --------------------------------------------------------------------------------------

		/*	echo "<br/><br/><b>".$Liste_des_parametres_receptionnes.":</b><br/>";
			foreach ($params as $nom => $valeur)
			{
				if(substr($nom,0,5) == 'vads_')
				{
					echo "$nom = $valeur <br/>";	
				}
			}*/
	}
}
