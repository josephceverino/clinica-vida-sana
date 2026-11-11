-- Agrega el médico asignado sin borrar las citas existentes.
-- Ejecutar una sola vez sobre una base ya creada.

USE clinica_vida_sana;

ALTER TABLE citas
    ADD COLUMN medico_id INT UNSIGNED NULL AFTER area_id,
    ADD CONSTRAINT fk_citas_medico
        FOREIGN KEY (medico_id)
        REFERENCES usuarios (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;