import random
import csv

# Function to generate random DOB
def random_dob():
    year = random.randint(1995, 2005)
    month = random.randint(1, 12)
    day = random.randint(1, 28)  # To avoid invalid dates
    return f"{year:04d}-{month:02d}-{day:02d}"

# List of sample names
first_names = ["Aarav", "Vihaan", "Vivaan", "Ananya", "Diya", "Saanvi", "Pari", "Zara", "Aryan", "Myra", "Advik", "Kavya", "Arjun", "Anika", "Kabir", "Navya", "Riya", "Shaurya", "Aadhya", "Veer", "Ishaan", "Saisha", "Reyansh", "Aaradhya", "Vihan", "Anvi", "Prisha", "Atharv", "Siya", "Arnav", "Amaira", "Yash", "Aanya", "Rudra", "Shivansh", "Eva", "Aryan", "Kiara", "Aarush", "Pihu", "Vedant", "Aashi", "Darsh", "Anaya", "Kartik", "Mishti", "Raghav", "Aadhira", "Laksh", "Nisha", "Dev", "Riya", "Krish", "Sara", "Arav", "Tanya", "Samar", "Nandini", "Rohan", "Priya", "Karan", "Sneha", "Vikram", "Meera", "Raj", "Kavita", "Amit", "Sunita", "Suresh", "Rekha", "Manoj", "Poonam", "Rakesh", "Geeta", "Anil", "Kiran", "Vinod", "Madhuri", "Rajesh", "Shobha", "Sanjay", "Usha", "Ashok", "Seema", "Prakash", "Anjali", "Ravi", "Komal", "Deepak", "Neha", "Vijay", "Pooja", "Santosh", "Swati", "Mahesh", "Kavita", "Naveen", "Rashmi", "Gopal", "Shanti", "Harish", "Lata", "Sohan", "Kusum"]

last_names = ["Sharma", "Verma", "Gupta", "Singh", "Yadav", "Patel", "Mishra", "Chauhan", "Tiwari", "Pandey", "Dubey", "Jain", "Agarwal", "Bansal", "Goyal", "Khandelwal", "Mehra", "Saxena", "Trivedi", "Chaturvedi", "Nair", "Menon", "Pillai", "Iyer", "Rao", "Reddy", "Naidu", "Murthy", "Shastri", "Joshi", "Desai", "Shah", "Mehta", "Kapoor", "Khanna", "Malhotra", "Chopra", "Bhatia", "Arora", "Sodhi", "Gill", "Kaur", "Kumar", "Das", "Saha", "Banerjee", "Mukherjee", "Chatterjee", "Dutta", "Ghosh", "Roy", "Sen", "Mitra", "Bhattacharya", "Chakraborty", "Majumdar", "Ganguly", "Basu", "Sarkar", "Pal", "De", "Nandi", "Mondal", "Halder", "Biswas", "Chakrabarti", "Mandal", "Paul", "Saha", "Kar", "Bose", "Dey", "Ray", "Chowdhury", "Sengupta", "Bagchi", "Mukhopadhyay", "Datta", "Chakravarty", "Nag", "Lahiri", "Sanyal", "Guha", "Mazumder", "Bandyopadhyay", "Chattopadhyay", "Gangopadhyay", "Sarkhel", "Bhattacharjee", "Chakrabortty", "Majumder", "Ganguly", "Basak", "Sarkar", "Pal", "Debnath", "Nandi", "Mondal", "Halder", "Biswas", "Chakrabarti", "Mandal", "Paul", "Saha", "Kar", "Bose", "Dey", "Ray", "Chowdhury", "Sengupta", "Bagchi", "Mukhopadhyay", "Datta", "Chakravarty", "Nag", "Lahiri", "Sanyal", "Guha", "Mazumder", "Bandyopadhyay", "Chattopadhyay", "Gangopadhyay", "Sarkhel", "Bhattacharjee", "Chakrabortty", "Majumder", "Ganguly", "Basak", "Sarkar", "Pal", "Debnath"]

# Generate 100 students
students = []
for i in range(11, 111):  # From 011 to 110
    roll = f"BEL-KOT-{i:03d}"
    first = random.choice(first_names)
    last = random.choice(last_names)
    name = f"{first} {last}"
    email = f"{first.lower()}.{last.lower()}@mail.com"
    dob = random_dob()
    category = "internal" if i % 2 == 1 else "external"
    password = "Welcome@123"
    students.append([name, email, roll, dob, category, password])

# Write to CSV
with open('additional_students.csv', 'w', newline='') as file:
    writer = csv.writer(file)
    for student in students:
        writer.writerow(student)

print("Generated additional_students.csv with 100 students.")