FROM php:8.2-apache
RUN apt-get update && apt-get install -y unzip curl
RUN curl -L -o project.zip "https://drive.google.com/uc?export=download&id=1OStBgBcfz5Zb5nvW-Lz1zajkn4uA9SIu" && unzip -o project.zip -d /var/www/html/ && rm project.zip
EXPOSE 80
