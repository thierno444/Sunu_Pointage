#include <SPI.h>
#include <MFRC522.h>
#include <Servo.h>

// Broches et composants
#define PIN_LED_ROUGE 7
#define PIN_LED_VERTE 6
#define PIN_BUZZER 8
#define PIN_SERVO 4  
#define PIN_RST 9
#define PIN_SDA 10

MFRC522 rfid(PIN_SDA, PIN_RST);
Servo servo;

void setup() {
 // Initialisation des composants
 SPI.begin();
 rfid.PCD_Init();
 pinMode(PIN_LED_ROUGE, OUTPUT);
 pinMode(PIN_LED_VERTE, OUTPUT);
 pinMode(PIN_BUZZER, OUTPUT);
 servo.attach(PIN_SERVO);
 servo.write(0); // Position initiale du servomoteur à 0°
 
 // Initialisation de la communication série
 Serial.begin(9600);
 Serial.println("System pret. Commandes disponibles: OPEN, CLOSE");
}

void loop() {
 // Vérifier les commandes manuelles - Version simplifiée
 if (Serial.available() > 0) {
   String command = Serial.readStringUntil('\n');
   command.trim();
   
   if (command == "OPEN") {
     servo.write(90);
   } else if (command == "CLOSE") {
     servo.write(0);
   }
 }

 // Vérifier si une carte est détectée
 if (rfid.PICC_IsNewCardPresent()) {
   if (rfid.PICC_ReadCardSerial()) {
     // Lire l'UID de la carte
     String cardId = getUID(rfid.uid.uidByte, rfid.uid.size);
     
     // Envoyer l'UID via la connexion série
     Serial.println(cardId);
     
     // Attendre la réponse du serveur
     while (Serial.available() == 0) {
       // Attente de réponse
     }
     
     // Lire la réponse
     String response = Serial.readString();
     response.trim();
     handleResponse(response);
     delay(2000);
     
     // Arrêter la communication RFID
     rfid.PICC_HaltA();
   }
 }
}

// Fonction pour lire l'UID de la carte
String getUID(byte* uid, byte size) {
 String uidStr = "";
 for (byte i = 0; i < size; i++) {
   uidStr += String(uid[i], HEX);
 }
 uidStr.toUpperCase();
 return uidStr;
}

// Fonction pour gérer la réponse du serveur
void handleResponse(String response) {
 if (response == "valid") {
   // Accès validé
   digitalWrite(PIN_LED_VERTE, HIGH);
   buzzerBeep(1, 100); // Bip court
   rotateServo(90, 10000); // Ouvrir le servomoteur pour 10 secondes
   digitalWrite(PIN_LED_VERTE, LOW);
 } else if (response == "invalid") {
   // Accès refusé
   digitalWrite(PIN_LED_ROUGE, HIGH);
   buzzerBeep(1, 500); // Bip long
   delay(1000);
   digitalWrite(PIN_LED_ROUGE, LOW);
 } else {
   // Erreur ou UID inconnu
   buzzerBeep(2, 300); // Deux bips pour signaler une erreur
 }
}

// Fonction pour gérer les bips du buzzer
void buzzerBeep(int count, int duration) {
 for (int i = 0; i < count; i++) {
   digitalWrite(PIN_BUZZER, HIGH);
   delay(duration);
   digitalWrite(PIN_BUZZER, LOW);
   delay(100);
 }
}

// Fonction pour faire tourner le servomoteur avec temporisation
void rotateServo(int angle, int delayMs) {
 servo.write(angle); // Tourner à l'angle spécifié
 delay(delayMs);     // Attendre le temps spécifié
 servo.write(0);     // Retourner à la position initiale
}