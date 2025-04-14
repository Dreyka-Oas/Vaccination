CREATE TABLE users (
    matricule VARCHAR(50) PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role_id INT REFERENCES roles(role_id),
    date_creation TIMESTAMP WITH TIME ZONE DEFAULT now(),
    matricule_creation VARCHAR(50) REFERENCES users(matricule)
);

CREATE TABLE roles (
    role_id SERIAL PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO roles (role_name) VALUES ('admin'), ('vaccine'), ('vaccinant');

CREATE TABLE type_vaccins (
    type_vaccin_id SERIAL PRIMARY KEY,
    nom_type VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    date_creation TIMESTAMP WITH TIME ZONE DEFAULT now(),
    matricule_creation VARCHAR(50) REFERENCES users(matricule)
);

CREATE TABLE vaccins (
    vaccin_id SERIAL PRIMARY KEY,
    nom_vaccin VARCHAR(255) NOT NULL,
    description TEXT,
    campagne_id INT NOT NULL REFERENCES campagnes(campagne_id) ON DELETE RESTRICT,
    matricule_creation VARCHAR(50) NOT NULL REFERENCES users(matricule) ON DELETE RESTRICT,
    date_creation TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vaccin_type_associations (
    vaccin_id INT NOT NULL REFERENCES vaccins(vaccin_id) ON DELETE CASCADE, 
    type_vaccin_id INT NOT NULL REFERENCES type_vaccins(type_vaccin_id) ON DELETE CASCADE,
    PRIMARY KEY (vaccin_id, type_vaccin_id)
);

CREATE TABLE lots (
    lot_id SERIAL PRIMARY KEY,
    nom_lot VARCHAR(255) UNIQUE NOT NULL,
    date_expiration DATE NOT NULL,
    campagne_id INTEGER NOT NULL REFERENCES campagnes(campagne_id) ON DELETE RESTRICT,
    vaccin_id INTEGER NOT NULL REFERENCES vaccins(vaccin_id) ON DELETE RESTRICT,
    date_creation TIMESTAMP WITH TIME ZONE DEFAULT now(),
    matricule_creation VARCHAR(50) REFERENCES users(matricule) ON DELETE SET NULL
);

CREATE TABLE vaccination_records (
    vaccination_record_id SERIAL PRIMARY KEY,
    lot_id INTEGER NOT NULL REFERENCES lots(lot_id),
    campagne_id INTEGER NOT NULL REFERENCES campagnes(campagne_id),
    date_vaccination DATE,
    matricule_patient VARCHAR(50),
    date_creation TIMESTAMP WITH TIME ZONE DEFAULT now(),
    matricule_creation VARCHAR(50) REFERENCES users(matricule)
);
