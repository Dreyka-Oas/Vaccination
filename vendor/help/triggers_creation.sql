-- triggers_creation.sql

-- Function to automatically set date_creation on INSERT if it's NULL
CREATE OR REPLACE FUNCTION set_date_creation()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.date_creation IS NULL THEN
        NEW.date_creation := NOW();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Fonction pour enregistrer l'historique lors des opérations UPDATE et DELETE (VERSION CORRIGÉE)
CREATE OR REPLACE FUNCTION record_history()
RETURNS TRIGGER AS $$
DECLARE
    v_old_value TEXT;
    v_new_value TEXT;
    v_matricule VARCHAR(50);
    v_record_id INTEGER; -- Nouvelle variable pour la clé primaire générique
BEGIN
    -- Déterminer matricule et clé primaire en fonction du nom de la table
    IF TG_TABLE_NAME = 'users' THEN
        v_matricule := OLD.matricule_creation; -- Ou NEW.matricule_creation si pertinent
        v_record_id := OLD.matricule::INTEGER; -- Cast matricule to INTEGER if needed for historique.record_id
    ELSIF TG_TABLE_NAME = 'type_vaccins' THEN
        v_matricule := OLD.matricule_creation;
        v_record_id := OLD.type_vaccin_id;
    ELSIF TG_TABLE_NAME = 'campagnes' THEN
        v_matricule := OLD.matricule_creation;
        v_record_id := OLD.campagne_id; -- Utiliser campagne_id pour la table campagnes
    ELSIF TG_TABLE_NAME = 'lots' THEN
        v_matricule := OLD.matricule_creation;
        v_record_id := OLD.lot_id;
    ELSIF TG_TABLE_NAME = 'vaccination_records' THEN
        v_matricule := OLD.matricule_creation;
        v_record_id := OLD.vaccination_record_id;
    ELSE
        v_matricule := NULL; -- Gérer si aucune colonne matricule pertinente
        v_record_id := NULL; -- Gérer si aucune clé primaire pertinente
    END IF;

    IF TG_OP = 'UPDATE' THEN
        v_old_value := row_to_json(OLD);
        v_new_value := row_to_json(NEW);
        INSERT INTO historique (table_name, record_id, action_type, matricule_user, old_value, new_value)
        VALUES (TG_TABLE_NAME, v_record_id, TG_OP, v_matricule, v_old_value, v_new_value); -- Utiliser v_record_id
    ELSIF TG_OP = 'DELETE' THEN
        v_old_value := row_to_json(OLD);
        INSERT INTO historique (table_name, record_id, action_type, matricule_user, old_value)
        VALUES (TG_TABLE_NAME, v_record_id, TG_OP, v_matricule, v_old_value); -- Utiliser v_record_id
    END IF;

    RETURN OLD; -- Pour BEFORE DELETE et BEFORE UPDATE triggers
END;
$$ LANGUAGE plpgsql;


-- Triggers to set date_creation for each table (except historique and users as it's already DEFAULT now())
CREATE TRIGGER set_date_creation_type_vaccins
BEFORE INSERT ON type_vaccins
FOR EACH ROW EXECUTE FUNCTION set_date_creation();

CREATE TRIGGER set_date_creation_campagnes
BEFORE INSERT ON campagnes
FOR EACH ROW EXECUTE FUNCTION set_date_creation();

CREATE TRIGGER set_date_creation_lots
BEFORE INSERT ON lots
FOR EACH ROW EXECUTE FUNCTION set_date_creation();

CREATE TRIGGER set_date_creation_vaccination_records
BEFORE INSERT ON vaccination_records
FOR EACH ROW EXECUTE FUNCTION set_date_creation();


-- Triggers to record history for UPDATE and DELETE operations for each table (except historique)
CREATE TRIGGER record_history_type_vaccins_update
BEFORE UPDATE ON type_vaccins
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_type_vaccins_delete
BEFORE DELETE ON type_vaccins
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_campagnes_update
BEFORE UPDATE ON campagnes
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_campagnes_delete
BEFORE DELETE ON campagnes
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_lots_update
BEFORE UPDATE ON lots
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_lots_delete
BEFORE DELETE ON lots
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_vaccination_records_update
BEFORE UPDATE ON vaccination_records
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_vaccination_records_delete
BEFORE DELETE ON vaccination_records
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_users_update
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION record_history();

CREATE TRIGGER record_history_users_delete
BEFORE DELETE ON users
FOR EACH ROW EXECUTE FUNCTION record_history();