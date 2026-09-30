--liquibase formatted sql

--changeset scout-market:V005-minimiser-donnees-audit splitStatements:true endDelimiter:;
--comment: Retire les adresses e-mail des audits et anonymise les traces anciennes ou sans compte

UPDATE scout_market.audit_mouvement_stock audit
SET utilisateur_libelle = btrim(utilisateur.prenom || ' ' || utilisateur.nom)
FROM scout_market.utilisateur utilisateur
WHERE audit.utilisateur_id = utilisateur.id;

UPDATE scout_market.audit_mouvement_stock
SET utilisateur_id = NULL,
    utilisateur_libelle = 'Utilisateur anonymisé',
    etat_avant = etat_avant #- '{mouvement,utilisateur_id}' #- '{mouvement,annule_par_id}',
    etat_apres = etat_apres #- '{mouvement,utilisateur_id}' #- '{mouvement,annule_par_id}'
WHERE utilisateur_id IS NULL
   OR created_at <= CURRENT_TIMESTAMP - INTERVAL '1 year';
