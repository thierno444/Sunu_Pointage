# 🎯 SunuPointage

**SunuPointage** est un système intelligent de gestion de pointage conçu pour les structures de formation. Il intègre une technologie RFID, une interface intuitive et une électronique embarquée pour assurer un suivi précis des présences, absences et retards, tout en optimisant l’accès sécurisé aux locaux.

---

## 📝 Description du Projet

Ce projet permet aux **apprenants** et **employés** de pointer leur présence à l’aide de **cartes RFID**, tout en offrant aux **administrateurs** et **vigiles** une interface web moderne pour superviser, gérer et historiser les accès.

---

## 🚀 Fonctionnalités Clés

### 👤 Utilisateurs (Apprenants & Employés)
- Pointage rapide via carte RFID.
- Ouverture automatique de la porte (fermeture après 10 secondes).
- Indicateurs visuels et sonores (LED & buzzer) pour feedback instantané.

### 🛡️ Vigile
- Connexion sécurisée par email/mot de passe.
- Vérification des identités (photo, nom, prénom, matricule).
- Contrôle manuel de l’ouverture/fermeture des portes via interface.

### 🧑‍💼 Administrateur
- Gestion complète des utilisateurs :
  - Ajout, modification, suppression, blocage.
  - Import en masse via fichiers CSV.
- Attribution et gestion des cartes RFID.
- Modification des pointages (absences justifiées, congés, etc.).
- Visualisation détaillée des historiques :
  - Journaliers, hebdomadaires et mensuels (absences & retards).

---

## 🧰 Technologies Utilisées

- **Frontend** : Angular
- **Backend** : Laravel
- **Base de données** : MongoDB (peut être adaptée à d'autres SGBD)
- **Matériel embarqué** :
  - Microcontrôleur (ex: PIC16F877A)
  - Lecteur RFID
  - Servomoteur (porte)
  - LEDs & Buzzer

- **Outils de conception & gestion** :
  - Figma (maquettes & prototypes)
  - Trello (gestion agile du projet)

---

## ⚙️ Prérequis

- `Node.js` & `npm` pour le frontend.
- `PHP` & `Composer` pour le backend.
- MongoDB (ou serveur MySQL selon adaptation).
- Matériel électronique correctement câblé (RFID, servo, etc.).

---

## 📦 Installation

### 🔧 Frontend (Angular)

```bash
cd pointage-frontend
npm install
ng serve
